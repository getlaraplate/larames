# MES Module — Developer Reference

Manufacturing Execution System for Laraplate. Turns confirmed demand and item
master data into executable production, tracks shop-floor progress, consumes
materials, traces lots/serials, records quality and downtime, and exposes
production KPIs.

## Boundaries

- **Depends on ERP** (one-way: ERP knows nothing of MES). Physical FKs point at
  ERP `companies`, `items`, `warehouses`, `sales_orders`, `sales_order_lines`.
- Every table is prefixed `mes_` and centralised in `MESTables`.
- Stock movements are defined by the MES `StockMovementRecorder` contract and
  executed by the ERP adapter `ErpStockMovementRecorder`.
- Component holds go through the ERP `StockReservationService` under the opaque
  source alias `mes.production_order_material` (see «Component reservation»).
- Production order numbers are allocated by ERP `DocumentNumberAllocator` using
  `DocumentType::ProductionOrder`.

## Entities

| Area | Models |
|------|--------|
| Work centers | `WorkCenter`, `WorkCenterCalendar` |
| BOM | `Bom`, `BomLine` (validity window; audited) |
| Routing | `Routing`, `RoutingOperation` (validity window; audited) |
| Production | `ProductionOrder` (immutable BOM/routing snapshots; audited), `ProductionOrderOperation` |
| Materials | `MaterialConsumption` |
| Traceability | `LotNumber`, `SerialNumber`, `LotLineage` |
| Quality | `QualityPlan`, `QualityPlanCharacteristic` (validity window), `QualityCheck`, `QualityCheckMeasurement`, `NonConformance` |
| Downtime | `Downtime` |
| Shifts | `Shift`, `ShiftInstance`, `OperatorLog` |
| Machine connectivity | `MachineSource` (linked to a service account), `MachineProfile`, `MachineDevice`, `MachineSignal`, `UnmappedSignal`, `MachineMessage`, `MachineIncident`, `MachineAgentConfig`, `MachineAgentConfigReport` |

## Core flow

1. **Create** (`ProductionOrderService::create`) — allocates the number and
   freezes the effective BOM lines and routing operations into immutable JSON
   snapshots (`bom_snapshot`, `routing_snapshot`). Status `draft`.
2. **Release** (`release`) — materialises `ProductionOrderOperation` rows from
   the routing snapshot. Status `released`. Once the release is persisted,
   `ProductionOrderReleased` reserves the order's BOM components in ERP stock
   (see «Component reservation»).
3. **Execute operations** (`ProductionOrderOperationService`) —
   `start`/`complete`/`skip`; efficiency = standard / actual, clamped
   `[0, 999.99]`. Completing an operation logs the operator (`OperatorLog`),
   dispatches `BackflushMaterialsJob`, and creates the in-process
   `QualityCheck` from any active plan (see «Quality plans»).
4. **Backflush** (`BackflushMaterialsJob`, idempotent) — consumes snapshot BOM
   lines marked `backflush` whose `routing_operation_id` matches the operation
   (or the order's last operation when null — decision D5). Each line reads
   on-hand stock via `StockReader` and consumes the **available** quantity: the
   stock `out` is posted for what exists, and any shortfall flags
   `mes_material_consumptions.stock_shortage` (with a negative `variance`) and
   emits `MaterialShortageDetected`. Non-blocking and stock never goes negative.
   After the stock-out, the ERP reservation of the line is closed for the part
   that was held (see «Component reservation»).
5. **Order state machine** — `draft → released` (`release`, emits
   `ProductionOrderReleased`) `→ in_progress` (the first `ProductionOrderOperationService::start()`
   on a released order, emits `ProductionOrderStarted`) `→ completed` (`complete`, emits
   `ProductionOrderCompleted`). `cancel` is allowed from draft, released and in progress: the
   operations that did not complete (planned or running) become `skipped`, and
   `ProductionOrderCancelled` is emitted and releases the order's component reservations. Operations emit `OperationStarted`,
   `OperationCompleted` and `OperationSkipped`. Events carry ids only and are dispatched
   after the transition is persisted.
6. **Complete order** (`complete`) — receives the finished goods into the order's warehouse
   (`FinishedGoodsReceiptService`, see below), refused (`DomainException`) while any
   operation is `in_progress`; sets produced quantity; generates the
   finished `LotNumber` when the item is lot/serial-traced; creates the
   final-inspection `QualityCheck` from any active plan.

## Sales-order-driven creation

Confirming an ERP sales order dispatches `Modules\ERP\Events\SalesOrderConfirmed`
(fired on create-as-confirmed and on the `draft → confirmed` transition). MES
listens with the queued `CreateProductionOrdersForSalesOrder`, which delegates to
`SalesOrderProductionPlanner`. Per line the planner creates a draft production
order only when the line has an item **with an active BOM**; it plans the
outstanding quantity (`qty_ordered − qty_delivered`) and links the order back via
`sales_order_id` / `sales_order_line_id`. Planning is idempotent per line (an
existing order short-circuits), so replays and re-confirmations never duplicate.
The receiving warehouse comes from `ProductionWarehouseResolver` (config map →
company's sole warehouse → skip when ambiguous); planned dates come from
`ProductionLeadTimeEstimator` (routing standard minutes ÷ daily minutes, or the
default lead time when the item has no routing). Purchased/service lines, already
delivered lines and multi-warehouse-ambiguous lines are skipped with a reason
(`ProductionPlanningSkipReason`) rather than guessed.

## Quality plans

A `QualityPlan` is a date-effective (`version`/`valid_from`/`valid_to`/`is_active`)
set of expected characteristics (`QualityPlanCharacteristic`: `nominal`,
`lower_limit`, `upper_limit` — the same shape as `QualityCheckMeasurement`) for an
item, optionally scoped to a `routing_operation_id`. When an operation completes,
`QualityCheckPlanner` resolves the plan for `(item, routing_operation)` and creates
a pending `QualityCheck` linked to it; when the order completes it resolves the plan
with `routing_operation_id = null` (final inspection). Creation is non-blocking (no
plan → no-op) and idempotent per `(order, plan, operation)`. Operators later run the
existing `execute` action, which evaluates measurements and opens a non-conformance
on failure.

## Finished goods receipt and valuation

Completing an order posts an inbound movement of `quantity_produced` for the finished item in
the order's warehouse, in the same transaction as the completion (a failing receipt rolls the
completion back; nothing is posted for a quantity of zero). The unit cost is the order's
material cost over the quantity produced. The material cost is read through the
`ProductionCostReader` contract (`ErpProductionCostReader`): the sum of quantity x unit cost
of the ERP stock-outs sourced to the order, valued by the ERP (FIFO layer or weighted
average). `ErpStockMovementRecorder` now passes the production order as the movement source,
so every MES movement is traceable to it, and `StockMovementData` carries an optional
`unit_cost` for inbound movements. Labour and machine time have no cost rate, so they add
nothing. A backflush still queued when the order completes is not part of the cost.

## Stock shortage

`StockReader` (ERP-backed `ErpStockReader` over `StockLevel`, the warehouse-pinned
reservations and the company-wide null-warehouse holds) is the read side of the stock boundary. Backflush and manual consumption read availability and consume
what exists: the stock-out is posted for the available quantity
(`quantity_consumed`), the shortfall is recorded as a negative `variance` with
`stock_shortage = true`, and `MaterialShortageDetected` is emitted. This keeps the
stock ledger truthful (never negative, which the ERP `recordOutbound` rejects) and
turns a hard ERP exception into a structured, non-blocking early warning. The
queued `NotifyMaterialShortage` listener sends `MaterialShortageNotification` to
the recipients configured by role (`mes.notifications.stock_shortage`).

## Component reservation

A released production order holds its BOM components in ERP stock, so a component promised
to one order is no longer available to another. MES calls the ERP `StockReservationService`
through `ComponentReservationService`; ERP only sees the opaque source alias
`mes.production_order_material` and never resolves a MES class.

- **Reserve at release.** `ProductionOrderService::release()` dispatches `ProductionOrderReleased`
  after the release transaction commits, and the synchronous `ReserveComponentsForProductionOrder`
  calls `reserveForOrder()`. For every frozen snapshot line that has an `item_id` and a
  `material_line_id` (whatever its consumption method) it computes the requirement as the per-unit
  quantity times the order quantity (`quantity_produced`, else `quantity_planned`) and takes a
  `hard` reservation of `min(requirement, company-wide available)` pinned to the order's warehouse.
  It is best effort: a short component reserves what exists (nothing when none is left) and never
  blocks the release; there is no early shortage event, the existing `MaterialShortageDetected`
  still fires at backflush. A per-line `reserve()` error (a lost availability race, a lock timeout,
  or a validation failure) is caught and logged with context (company, order, item, line); it never
  propagates out of the release transition, and the other lines still reserve.
- **Release at cancel or completion.** `ProductionOrderCancelled` runs
  `ReleaseComponentsForProductionOrder` and `ProductionOrderCompleted` runs
  `ReleaseComponentsAfterCompletion`; both release the live hold of every snapshot line (idempotent;
  consumed quantity is terminal). Releasing on completion is what keeps a completed order — which can
  no longer be cancelled — from leaking its backflush shortfall and its manual-consumption holds.
- **Consume at backflush.** `BackflushMaterialsJob` keeps its path (post the stock-out, record
  `quantity_consumed`/`variance`, flag `stock_shortage`, emit `MaterialShortageDetected`) and then,
  for the line, closes `min(quantity consumed, quantity still hard-reserved)` through
  `consumeForLine()`. The consumable is sized as the reader's availability **plus this order's own
  hard hold for the line**: `ErpStockReader` subtracts every warehouse-pinned hold, this order's
  included, so adding its own hold back lets the order consume the stock it reserved for itself while
  the ceiling stays `on hand − other orders' holds`. A company-wide null-warehouse (sales) hold is
  never the order's own, so it is not added back and stays subtracted: backflush can never consume
  stock a sales order reserved. Only the reserved part is closed: a line that
  never reserved is a no-op, and stock beyond the reservation is consumed physically as before. The
  reservation close is not in the same transaction as the stock-out.
- **Per-warehouse availability.** `ErpStockReader::availableQuantity()` is the on-hand `StockLevel`
  of the `(item, warehouse)` minus the soft/hard reservations pinned to that same warehouse **and**
  the item's company-wide null-warehouse holds (sales reservations). A
  soft row counts by state alone, so an expired but not yet swept soft hold still counts until the
  ERP expiry sweep closes it. The null-warehouse subtraction is conservative: a company-wide hold is
  taken off every warehouse's availability (it may under-report when stock is spread across
  warehouses) so MES can never oversell stock another module reserved company-wide.
- **`material_line_id` keying.** The reservation `source_id` is the snapshot line's
  `material_line_id`, stamped by the `ProductionOrder` `created` boot hook on every creation path
  (service, factory, import, direct create) as `order id * 1000 + line index`. It is unique to one
  order and stable for the whole lifecycle (reserve, release, consume). The template `bom_line_id`
  cannot be used: it is shared by every order built from the same BOM and would pool holds across
  them. A line that already carries a `material_line_id` (a test fixture) is left untouched; a line
  that still has none after stamping is skipped by reserve, release and consume.

Known limitations:

- The `* 1000` scheme bounds a production order to fewer than 1000 component lines; the `creating`
  boot hook rejects a snapshot that reaches 1000 lines with a `DomainException` (before insert), so a
  larger BOM can never silently collide with the id range of the next order.
- Availability is read two ways: the reserve clamp uses the company-wide ERP availability, while the
  warehouse-pinned hold is read per warehouse. The reader does subtract the company-wide
  null-warehouse (sales) holds, but conservatively (off every warehouse), so per-warehouse ATP stays
  a deferred non-goal.
- The backflush closes the line's reservation in a separate transaction from the stock-out movement,
  so a crash between the two can leave the stock posted while the hold is still open until the next
  release/cancel/completion of the order.

## Services

`BomExplosionService` (multi-level explosion + active BOM), `RoutingResolverService`
(date-effective routing), `ProductionOrderService`, `ProductionOrderOperationService`,
`LotTracingService` (forward/backward genealogy), `QualityCheckService`
(limits → non-conformance), `NonConformanceService` (dispositions; rework spawns a
linked order), `CapacityService` (work-center load, available minutes, overload), `OeeCalculatorService`
(A×P×Q, clamped), `DowntimeService`, `ShiftVerificationService`,
`SalesOrderProductionPlanner` (auto-creation from confirmed sales orders, with
`ProductionWarehouseResolver` and `ProductionLeadTimeEstimator`),
`QualityPlanResolver` + `QualityCheckPlanner` (auto quality checks on completion),
`ErpStockReader` (`StockReader` read for shortage detection: on-hand minus warehouse-pinned reservations and company-wide null-warehouse sales holds),
`ComponentReservationService` (reserve, release and consume ERP reservations for a production order's BOM
components).

## HTTP surface

No custom routes. Entities are reachable through Core's generic CRUD
(`/app/crud/{verb}/mes/{entity}`). Domain verbs use Core's domain-action
registry (`POST /app/crud/{action}/mes/{entity}`): production-orders
`release`/`complete`/`cancel`, operations `start`/`complete`/`skip`,
quality-checks `execute`, non-conformances `resolve`/`close`, downtimes `close`, work-centers `open_downtime`,
boms `explode`, lot-numbers `forward_trace`/`backward_trace`. Registered by
`MesDomainActionRegistrar`; authorized by `MesModelPolicy` against seeded
`{connection}.{table}.{action}` permissions (seeded by `MESDatabaseSeeder`
from `MESPermissions`). A downtime is opened with the `open_downtime` action on its
work center (payload `cause`, optional `production_order_operation_id` and `notes`), which
refuses a second open downtime on the same work center and emits `DowntimeOpened`
(`close` emits `DowntimeClosed`); the generic insert still exists for back-filling
history and bypasses those rules. Aggregate reads have no routes: the `ProductionDashboardWidget`
shows four counts (open orders, running operations, completed orders, open
non-conformances) cached for 60 seconds. OEE and capacity are materialised
(decision D10): `mes:kpis:materialize` (hourly, `--day=Y-m-d` to backfill) queues one
`MaterializeWorkCenterKpisJob` per active work center, which stores a `WorkCenterKpis`
(availability, performance, quality, OEE, capacity load, available minutes, overload flag)
in the cache through `WorkCenterKpiMaterializer` and `WorkCenterKpiStore`. Reads never
recompute: a day not materialised yet has no figure. The work-center list shows the
OEE of today.

## Capacity and downtime

`CapacityService::getCapacityLoad()` sums the standard minutes (setup + cycle ×
planned quantity) of the operations planned on a work center in a window. An
operation with planned dates counts in proportion to its overlap with the window; one
without dates counts whole when its order overlaps it.
`availableMinutes()` is the working minutes of the work center calendar in the window
(`WorkCalendar`: `WorkCenterCalendar` slots by `day_of_week`, several per day; a work
center with no slot works 08:00 to 16:00 every day) less every downtime overlapping it (`DowntimeService::outOfServiceMinutesWithin()`:
planned maintenance included, because the work center is out of service either way;
each downtime is clipped to the window and an open one runs until now), never negative.
OEE availability keeps excluding planned maintenance
(`unplannedMinutesWithin()`) and clips downtimes to the window the same way.
`checkOverload(work_center, from, to, ?available)` compares the load with the
available minutes, or with an explicit budget when one is passed.

Planning is forward and infinite-capacity. `scheduleOperations(order)` (called by
`ProductionOrderService::release()`) writes `planned_start_at` / `planned_end_at` on each
planned operation: it starts when the previous one ends (a parallel one with it), at the
first working instant of its own work center, and lasts its standard minutes of working
time; it never looks at what else is planned on that work center.
`estimateCompletionDate()` plans the operations still to do from now (or from the planned
start while that is ahead) and returns the end of the last one; with nothing left it
returns the last actual end, or the order's `planned_end_at` when it has no operation.
`rescheduleOperation(operation, work_center, ?planned_start_at)` moves the operation and,
given a date, plans it again from there on the new calendar. Starting an operation on a work center whose materialised
KPIs flag an overload emits `CapacityOverloadDetected` (non-blocking, nothing is
recomputed live), notified to the roles in `mes.notifications.capacity_overload`.

## Machine connectivity

User guide and protocol: `docs/MACHINE_CONNECTIVITY.md`. Step 1 of the machine data acquisition design:
the foundation, with no consumer of the machine data yet.

- **Ingress.** `POST api/v1/insert/mes/machine-messages` (`mes.api.machine-messages.insert`, no alias for the
  former path): Core's `AuthenticateApiRequest` authenticates the bearer token of the source's service account
  (`401` missing, invalid, expired or revoked; `403` outside the token's `allowed_cidrs`), then
  `ResolveMachineSource:{model},{operation}` checks the `api` permission (`insert` on `mes_machine_messages`; `401` for
  the anonymous user, `403` otherwise), resolves the source through `MachineSourceResolver` (`403` when the account
  speaks for no source) and refuses an inactive one (`403`), then the `mes-machine-ingest` throttle
  (`mes.machine.rate_limit_per_minute`, per service account), then `MachineDataIngestController` (`403` not an http
  canonical source, `413` body or sample limits, `422` `MachineEnvelopeValidator`) and `MachineMessageInbox::accept()`,
  which stores the raw message through `IdempotentWriter` (insert-or-ignore on five drivers, a unique-violation
  fallback on Oracle), records `seq_gap` and `clock_skew` incidents and queues `ProcessMachineMessageJob`.
  A refusal that names a source is one `auth_failure` incident per source every five minutes, with the detail
  reason `missing_permission` or `inactive_source`; a caller with no source is only logged.
- **Switch, limits and the agent role.** The routes sit behind Core's `expose_api` switch (`403` while it is off, the
  default). Core's `api` limiter counts per token once authenticated (`core.api.rate_limit_per_minute`). Sources are
  service accounts (`users.is_service_account`) linked by `mes_machine_sources.user_id` (nullable, unique, counting
  soft-deleted sources; only service accounts qualify). `MESDatabaseSeeder` creates the `api` role `mes-machine-agent`
  (`MACHINE_AGENT_ROLE`; the seeder `dependsOn()` `PermissionRefreshSeeder`) with `insert` on `mes_machine_messages`,
  `select` on `mes_machine_agent_configs` and `insert` on `mes_machine_agent_config_reports`, granted without detaching,
  plus an ACL on the configurations' `select` that narrows the role to its own active source (`RelationFilter` on
  `source`, `user_id = @user.id` and `is_active = true`; an installation seeded before the active-source condition keeps
  its stored ACL, which the seeder never overwrites). `MachineMessage`, `MachineAgentConfig` and `MachineAgentConfigReport` implement
  `RestrictsCrudWrites`: Core's generic CRUD refuses their writes, so the permissions open the dedicated routes alone.
  `bootstrap/app.php` counts named limiters in Redis (`throttleWithRedis()`) except under the test runner.
- **Job.** `ProcessMachineMessageJob` (queue `mes.machine.queue`, `WithoutOverlapping` keyed by source,
  3 tries) owns retries, status and the `message_failed` incident; the pipeline is
  `MachineMessageProcessor`: `NormalizerRegistry` (`canonical`, `mapped_json`), `SignalResolver` (one cached
  map per source, forgotten by `MachineConfigurationObserver`), `OperationAttributor` (explicit reference,
  else the single operation running at the sample time; `prepare()` loads the candidates of a message in one
  query), `UnmappedSignalRecorder` (batched; counts once per message and never on a reprocess), and the typed
  events `MachineStateObserved`, `PartsCounted`, `ProbeMeasured`, `ProcessValuesSampled`.
- **Tables.** Configuration tables (`mes_machine_sources/profiles/devices/signals`) extend Core's model with
  soft delete; pipeline tables (`mes_machine_messages`, `mes_unmapped_signals`, `mes_machine_incidents`) are
  plain Eloquent models. Messages are `MassPrunable` (processed ones past `inbox_retention_days`).
- **Operations.** `MachineMessageReprocessor` (per message, per source range), `MachineWatchdog` and
  `mes:machine-watchdog` (every minute: `device_silent` incidents), `MachineIncidentRecorder` and the
  `NotifyMachineIncident` listener (`mes.notifications.machine_incident`), `MachineProfileService` (apply,
  import, export; `MachineSignal::rulesForRole()` validates a signal's config by role),
  `UnmappedSignalMapper`, `MachineSourceResolver` (the source of a service account, only users with `is_service_account`; soft-deleted sources
  never resolve).
- **Domain actions** (`MESPermissions`): `MachineMessage` `reprocess`; `MachineSource` `reprocess_range`;
  `MachineDevice` `apply_profile`; `MachineProfile` `export`. Tokens are issued on the service account's user page
  (Core user resource, API tokens, with `allowed_cidrs`), never on the source.
- **Edge agent backend** (plan `2026-10-15-mes-edge-agent-backend`). *Losses:* the optional `losses[]` of the envelope
  (`priority` `critical`/`bulk`, `from_ts`, `to_ts`, `samples` >= 1, `reason` `buffer_full`/`rejected_invalid`; schema and
  `MachineEnvelopeValidator`, lists enforced) reaches `MachineMessageProcessor::recordLosses()`, which records one
  `data_dropped` incident per marker (detail `{message_id, priority, from_ts, to_ts, samples, reason}`, `occurred_at` =
  `to_ts`) unless an incident with the same detail exists, so a reprocess, a duplicate or a second run adds none; the
  `(source_id, type, occurred_at)` index serves that lookup. *Configuration:* `mes_machine_agent_configs` (unique
  `(source_id, version)`, `document` json cast to an object tree) and `mes_machine_agent_config_reports`;
  `mes_machine_sources.active_config_id` (FK added by the configurations migration) and `last_config_version`.
  `MachineAgentConfigService`: `publish(MachineSource, stdClass|string $document, ?User)` validates structure
  (`laraplate-edge-config/1`, D3: top-level keys, integer `version`, `inputs`/`outputs`/`plugins` as arrays of objects
  with a string `id`; copy in `resources/edge/laraplate-edge-config-1.schema.json`; plugin fields are not checked, but
  the agent requires `sha256` and `manifest_sha256` per plugin and runs plugins only from its bootstrap `plugins_dir`), numbers the version from the locked
  source row, writes it into the document and activates it; `activate()` of a non-active version publishes it again as a
  new version (versions only grow, D2); `recordReport()` stores the report and opens `config_rejected`
  (`{version, outcome, errors}`) for `rejected` and `rolled_back` (D4). Routes: `GET api/v1/detail/mes/machine-agent-configs`
  (`mes.api.machine-agent-configs.detail`, `MachineAgentConfigController`: `200 {version, document}`, `304` when
  `?version=` equals the active version, `404` none active, `422` bad version) and
  `POST api/v1/insert/mes/machine-agent-config-reports` (`mes.api.machine-agent-config-reports.insert`,
  `MachineAgentConfigReportController`: `201`, `422` with nothing stored for a version not published for the caller's
  source), both behind `ResolveMachineSource` and the `mes-machine-agent-config` limiter
  (`mes.machine.config_rate_limit_per_minute` / `MES_MACHINE_CONFIG_RATE_LIMIT_PER_MINUTE`, per service account, shared by
  the two routes). `MachineSource::delete()` runs in one transaction and a hard delete clears `active_config_id` first.
  *Backoffice:* the source page has the relation managers `AgentConfigsRelationManager` (publish with a JSON editor,
  activate, view document; gated on the `update` policy of the source) and `AgentConfigReportsRelationManager`
  (read-only). Documents hold only `secret://` references.
- **MQTT (step 2).** `php artisan mes:machine-bridge` (`MachineBridgeCommand` over `MachineBridge`): connects
  through `MachineMessageSubscriber` (`PhpMqttSubscriber`, php-mqtt/client, MQTT 3.1.1, QoS 1, persistent
  session under `mes.machine.mqtt.client_id`), subscribes to `MqttMessageRouter::subscriptions()` (the
  effective topics of the active `mqtt` sources, re-read every 60 s), hands each message to `MqttIngest`
  (router, canonical envelope validation, `MqttPayloadEnvelope` wrapper for `sparkplug_b`, then
  `MachineMessageInbox::accept()`), writes the heartbeat `mes:machine:bridge-heartbeat` every 10 s, backs off
  1, 2, 4 ... 60 s on `MqttConnectionLost`, and stops on `stop()` (SIGTERM and SIGINT in the command) after
  the message it holds. A message that cannot be routed is dropped (log once a minute; an invalid canonical
  envelope opens a `message_failed` incident). `MachineWatchdog` opens `bridge_down` per active mqtt source when
  the heartbeat is older than 60 s or absent.
- **Sparkplug B.** `SparkplugBNormalizer` (`sparkplug_b`) over `SparkplugPayloadDecoder` and `ProtobufReader`
  (a wire-format reader; no protobuf library; fixtures encoded by `protoc` from the Tahu schema),
  `SparkplugTopic`, and `SparkplugAliasStore` (`mes_sparkplug_aliases`: alias to metric name per source and
  device, replaced at each birth, read by data). Devices are `{node}` or `{node}/{device}`; control and null
  metrics are skipped; `source_seq` is null (the Sparkplug `seq` wraps at 255).
- **Backoffice** (group "Machine connectivity"): sources, devices with a signals relation manager, profiles
  (import and export), unmapped signals (map in place), the message inbox (reprocess), incidents.
- **States and downtimes (step 3).** `MachineStateRecorder` (listener of `MachineStateObserved`) calls
  `MachineStateIntervals::observe()/tagAlarm()` (idempotent history, split on late samples, merge of equal
  neighbours; `MachineTime` keeps milliseconds) and `MachineDowntimeDeriver::sync()` (downtime when the
  state is in `WorkCenter::isDowntimeState()` and the stop is longer than `micro_stop_threshold_seconds`;
  times only are updated, cause and notes kept). `DowntimeService::openFromMachine()/closeFromMachine()`
  write through `Downtime::writingAsMachine()`; `DowntimeCauseResolver` maps state and alarm code to a cause
  (default `Unclassified`). `MachineConnectivity::isConnected()` (active device of an active source with a
  state signal) gates manual downtimes and the ISO 22400 branch of `OeeCalculatorService::availability()`.
  `mes:machine-open-stops` (every minute) opens the downtime of stops still open; `MachineWatchdog` records
  and closes the synthetic `Offline` interval. `WorkCenterKpis::$incomplete_data` and the `mes:kpi:v2:` cache
  key. User guide: `docs/MACHINE_CONNECTIVITY.md`.
- **Counts (step 4).** `PartsCountRecorder` (listener of `PartsCounted`) writes `mes_machine_counts` rows
  (`MachineCount`, delta in the column of the signal's role, `raw_value`, unique `(signal_id, ts)`) through
  `IdempotentWriter`, with `CounterDelta::between()` (cumulative/delta mode, rollover, reset) and recomputes the
  row after a late insert. `OperationCountTally::refresh()` sums rows into `machine_good_quantity` and
  `machine_scrap_quantity` and fires `OperationTargetReached` once (`target_reached_at`).
  `ProductionOrderOperationService::complete()` prefills `declared_*`; `OperationQuantityDeclarer::declare()`
  is the audited writer (`mes_operation_quantity_audits`); `MachineCountAssigner::assign()` attributes
  unattributed counts by time range. `OeeCalculatorService::performance()` and `quality()` use the counts when
  the window has any (ideal cycle of the operation, or `60 / capacity_per_hour` for unattributed counts); cache
  key `mes:kpi:v3:`. User guide: `docs/MACHINE_CONNECTIVITY.md`.
- **Probe measurements (step 5).** `ProbeMeasurementRecorder` (listener of `ProbeMeasured`) takes a sample to the
  check of its operation whose plan holds the signal's characteristic (`QualityCheckService::record()`, then
  `isComplete()` and `resolve()` when pending), or to `mes_machine_unattributed_measurements`
  (`UnattributedMeasurement`); an out-of-limit value dispatches `OutOfToleranceMeasured` once. Idempotency by an
  existence check under `lockForUpdate` on the signal row (no unique index on measurements).
  `QualityCheckPlanner::createCheck()` calls `UnattributedMeasurementAttacher::attachFor()`;
  `UnattributedMeasurementAssigner::assign()` is the manual path (Filament "Unattributed measurements").
  `QualityCheckService::execute()` = `record()` + `resolve()`. User guide: `docs/MACHINE_CONNECTIVITY.md`.
- **Process values (step 6).** `ProcessValueRecorder` (listener of `ProcessValuesSampled`) hands process-value
  samples (numeric, not older than the raw retention) to the `ProcessValueStore` bound by
  `mes.machine.process_store`; `RelationalProcessValueStore` writes `mes_process_samples` in batches through
  `IdempotentWriter::insertMany()`, marks minutes in `mes_process_dirty_buckets` (upsert, so a re-mark changes
  `marked_at`), `rollup()` rebuilds `1m` aggregates from raw and `1h` from the minutes (bad samples excluded,
  never from pruned raw), `prune()`/`pruneAggregates()` apply the retentions. `mes:machine-rollup` (every
  minute) and `mes:machine-prune-process-values` (daily). `ProcessSummarizer::summarize()` upserts
  `mes_operation_process_summaries` (only with a count not below the stored one) from the unique queued
  `SummarizeOperationProcessValuesJob`, dispatched by `SummarizeOperationProcessValues` on `OperationCompleted`
  and by the recorder for completed operations when new samples were stored; `LotTracingService::processSummaries()`. User guide:
  `docs/MACHINE_CONNECTIVITY.md`. Everything in the design is built.

## Backoffice

The Filament resources are the superadmin backoffice. Work centers edit their
weekly calendar and BOMs their lines inline (relationship repeaters; line edits
are versioned). Production orders are list and edit only, with read-only
relation managers for operations, material consumptions, quality checks and
lots, and the edit page carries Release, Complete (produced quantity, optional lot
code) and Cancel header actions. They call `ProductionOrderService` and are shown by
`MesModelPolicy` (called directly: the gate would let a superadmin past the state
guard); a refused transition is a notification. Day-to-day execution still belongs to
the application built on the services and domain actions.

## Configuration

- `mes.queue.connection` / `mes.queue.name` — queue for backflush and PO jobs.
- `mes.lots.number_format` — lot code tokens `{YEAR}{MONTH}{DAY}{SEQ}`.
- `mes.production.default_warehouse` — `[company_id => warehouse_id]` map for
  sales-order-driven PO creation (falls back to the company's sole warehouse).
- `mes.production.daily_minutes` (`480`) / `mes.production.default_lead_time_days` (`5`) —
  routing-based lead-time estimation and its no-routing fallback. Settings of module `MES` (group
  `mes`), seeded by `MESDatabaseSeeder` and overlaid onto config; an operator's value survives a
  re-seed. Read as at least `1`, so a stored `0` behaves as `1`.
- `mes.notifications.stock_shortage.channels` / `.recipients.roles` — channels
  (default `database`) and recipient roles for the shortage notification.
- `mes.machine.queue` (`mes-machine`), `.max_samples` (5000), `.max_body_kb` (1024),
  `.clock_skew_seconds` (30), `.inbox_retention_days` (7), `.rate_limit_per_minute` (600) — machine
  connectivity; env `MES_MACHINE_*`. `mes.notifications.machine_incident` — channels and roles for incidents.

## Locked decisions

See `docs/superpowers/specs/2026-07-09-mes-module-decisions-design.md` (D1–D11):
complete scope, ERP-based numbering, dual sales-order link, operation-scoped
backflush with last-operation fallback, non-blocking shift warning, snapshot
immutability, DIFF audit on `ProductionOrder`/`Bom`/`Routing` and on their lines and operations (`BomLine`, `RoutingOperation`; per-table setting `versioning.strategy.{table}`, seeded to `DIFF`; lines and operations are validated on every write and deleted for good), materialised KPIs (decided, not implemented yet),
and no lock by use: a BOM or routing stays editable because every order runs from its own snapshot.

## Releases

Releases are cut by the maintainers with private tooling; do not bump versions or edit `CHANGELOG.md` by hand.

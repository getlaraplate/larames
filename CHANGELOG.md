# Changelog

All notable changes to this project will be documented in this file.

## [1.2.0] - 2026-10-21

### 🚀 Features

- *(mes)* Drop the BOM lock, version and validate lines and operations
- *(mes)* Unique work center codes, completion guard, downtime-aware capacity, backoffice repeaters and read-only order relations
- *(mes)* Domain interface labels in lang/{locale}/mes.php
- *(mes)* Typed lifecycle events, open_downtime action, materialised KPIs and capacity warning
- *(mes)* Release, complete and cancel actions on the production order edit page
- *(mes)* Calendar-based capacity, operation planning and completion estimate
- *(mes)* Receive finished goods into stock on order completion, valued at material cost
- *(mes)* Machine connectivity enums, table registry and configuration keys
- *(mes)* Machine source, profile, device and signal models
- *(mes)* Machine message, unmapped signal and incident models
- *(mes)* Laraplate-machine/1 schema, fixtures and envelope validator
- *(mes)* Machine message normaliser contract, registry, canonical and mapped_json
- *(mes)* Machine message inbox with idempotent insert, gap and clock skew incidents
- *(mes)* Machine data HTTP ingress with per-source tokens and limits
- *(mes)* Machine signal resolver and unmapped signal recorder
- *(mes)* Machine sample operation attribution
- *(mes)* Machine message processing job and typed machine events
- *(mes)* Machine message reprocessing, token actions and permissions
- *(mes)* Machine watchdog, incident notification and inbox pruning schedule
- *(mes)* Machine profile apply, import and export
- *(mes)* Machine connectivity backoffice for sources, devices and profiles
- *(mes)* Backoffice for unmapped signals, message inbox and incidents
- *(mes)* Reserve and consume BOM components through ERP reservations
- *(mes)* MQTT subscriber contract and configuration
- *(mes)* MQTT topic router and ingest path
- *(mes)* Machine bridge loop and mes:machine-bridge command
- *(mes)* Sparkplug B payload decoder
- *(mes)* Sparkplug_b normaliser with a persisted alias map
- *(mes)* Watchdog records bridge_down from the bridge heartbeat
- *(mes)* Php-mqtt adapter for the machine bridge
- *(mes)* Machine state interval table and the downtime columns of step 3
- *(mes)* Connected work centers refuse manual downtimes and lock machine downtime times
- *(mes)* Machine downtime opening, closing and cause resolution
- *(mes)* Machine state intervals and automatic downtimes
- *(mes)* Open stops sweep and synthetic offline state
- *(mes)* ISO 22400 availability for connected work centers and the incomplete-data flag
- *(mes)* Machine states in Filament and documentation of step 3
- *(mes)* Machine counts table and the quantity columns of operations
- *(mes)* Machine count deltas with rollover and reset
- *(mes)* Operation machine quantities and the target reached event
- *(mes)* Declared quantities with an audit trail and count assignment by time range
- *(mes)* ISO 22400 performance and quality from machine counts
- *(mes)* Piece counts in Filament and documentation of step 4
- *(mes)* Probe measurement columns and the unattributed measurements table
- *(mes)* Split quality check recording from resolution
- *(mes)* Probe measurements fill quality checks and announce out of tolerance values
- *(mes)* Attach waiting probe measurements to the check of their operation
- *(mes)* Probe measurements in Filament and documentation of step 5
- Mark machine ingest as a self-authenticated api route
- *(mes)* Add MES navigation group to the panel
- *(mes)* Process value tables and configuration
- *(mes)* Process value store with a relational driver and rollup
- *(mes)* Record process values, roll them up and prune them
- *(mes)* Permanent process summaries per operation and lot traceability
- *(mes)* Process summaries in Filament and documentation of step 6
- *(mes)* Mark the parts of a parent for related-record authorization
- *(mes)* The machine source tokens relation declares its return type
- *(mes)* Translation keys named like the database
- *(mes)* Machine sources authenticate as service accounts
- *(mes)* Translated resource names and navigation groups
- *(mes)* Machine ingest on the CRUD route through the service account
- *(mes)* Backoffice strings from the mes lang file
- *(mes)* Agent loss markers become data_dropped incidents
- *(mes)* Configuration delivery to the edge agent
- *(mes)* Agent configuration versions and reports on the source page

### 🐛 Bug Fixes

- *(mes)* Restrict deleting a company that still has work centers
- *(mes)* Key component reservations by a per-order material line id
- *(mes)* Machine connectivity review fixes
- *(mes)* Component reservation self-block, completion leak, reserve guard
- *(mes)* MQTT bridge review fixes
- *(mes)* Review fixes for machine states (working-time availability, downtime states default, merge keeps operator cause, stale sync, device lock)
- *(mes)* Close the minors of the machine states review
- *(mes)* Review fixes for machine counts (once-only target, per-device totals, retry re-tally, guarded quantity columns)
- *(mes)* Close the minors of the machine counts review
- *(mes)* Review fixes for probe measurements (locked check resolution, execute sees machine rows, attacher tolerates a bad row)
- *(mes)* Put the process summaries in the renamed machine connectivity group
- *(mes)* Review fixes for process values (queued unique summaries, no degraded summaries, rollup backlog, chunked prune, out-of-range values)
- *(mes)* Close the deferred items of process values and probe measurements
- *(mes)* Forget the signal map after commit on the model connection
- *(mes)* Operator guide on service accounts, resolver on the model's deleted-at column
- *(mes)* Machine messages refuse generic CRUD writes, named incident reasons
- *(mes)* The MES seeder declares its dependency on the permission refresh
- *(mes)* Serve agent configurations as published, safe numbering and deletes
- Machine agent ACL requires an active source, Activate shows validation errors, docs wording (final review M1, M3, M4, M5)
- *(mes)* A single late message (agent resend after an outage) no longer opens clock_skew
- *(mes)* Update module priority from 20 to 30
- *(mes)* Machine sources are administered in the backoffice only
- *(mes)* Loss ranges are ordered and a config rejection opens one incident per open version and outcome
- *(mes)* A resent loss marker is one incident, no clock-behind check over MQTT, upgrade note

### 💼 Other

- Own MES runtime setting definition tests

Moved from Core so Core tests no longer depend on MES.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_016bQ9UWLJjnRQA6sGEYNGPL

### 🚜 Refactor

- *(mes)* Update navigation group labels to include 'MES' prefix for consistency
- *(mes)* Streamline language files for actions, entities, fields, messages, navigation, status, and types
- Import Core VersionStrategy (D14)

### 📚 Documentation

- *(mes)* User guide covers history and validation of lines and operations
- *(mes)* Machine connectivity foundation documentation
- *(mes)* Document component reservation through ERP
- *(mes)* List the MQTT configuration keys in full
- *(mes)* MQTT bridge and Sparkplug B documentation
- *(mes)* Describe the navigation group order the panel test enforces
- API access control
- Verbatim AGPL-3.0 licence text, copyright in the README
- *(mes)* Edge agent backend (service accounts, configuration delivery, losses)
- *(mes)* Agent configuration stored as the same JSON value; agent ACL on the active source
- *(mes)* Drop the development database upgrade note
- Point the links to the getlaraplate organization
- Fix a typo in the work in progress notice
- Use the Laraplate brand logo in the README

### 🧪 Testing

- Follow the renamed API switch

### ⚙️ Miscellaneous Tasks

- *(mes)* Module priority 20, second tier after ERP
- *(mes)* Null-guard bom_snapshot in the stride guard
- *(mes)* Remove the unused nwidart module scaffold
- Point repository links to the getlaraplate organization
- Rename the Composer package to the getlaraplate vendor
- Align the package description with the repository description
- Align the module description with the repository description
- Call the organization CLA workflow
- Rename the repository and package from laraplate-<module> to lara<module>

## [1.1.1] - 2026-09-29

### 🚀 Features

- *(mes)* Order and head the production dashboard widget
- *(mes)* Create and edit pages say Close until something is unsaved

### 🐛 Bug Fixes

- *(docs)* Update README with PHP version badge and logo size adjustment

### 💼 Other

- Drop tracing_type patch migration on ERP items

The column now belongs to the ERP items schema (intrinsic Item attribute used
across purchase/warehouse/sales, not only production). MES keeps consuming
item.tracing_type in ProductionOrderService without owning the column.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>

### 🚜 Refactor

- *(migrations)* Fold column/index/enum alters into their create migrations
- *(migrations)* Fold FK-column alters into their create by moving the referenced table earlier
- *(migrations)* Fold cascade FK-column alters into their create migrations
- *(models)* Drop the IdeHelper mixins, declare the activation contract
- Narrow findOrFail to a single record with whereKey()->firstOrFail()
- *(mes)* Reject a non-numeric record id instead of casting it
- *(mes)* Drop unread rate limit setting and config default for the lot format
- *(mes)* Seed settings without the module prefix
- *(mes)* Lots.number_format setting name

### 📚 Documentation

- *(rag)* Describe how the module is released from the application

### ⚡ Performance

- *(migrations)* Index all foreign-key and row-scoping columns

### ⚙️ Miscellaneous Tasks

- Rimuove docblock ide-helper generati dai model
- Add IdeHelper mixin annotations to model classes
- Trim the docblocks the IdeHelper mixins left behind
- Rename composer package to swolley/laraplate-mes

## [1.1.0] - 2026-09-15

### 🚀 Features

- *(filament)* Expose the MES surfaces in the admin panel
- *(mes)* Enhance module description and add color support

### 🚜 Refactor

- *(tables)* Remove IconColumn from multiple tables
- *(models)* Remove unnecessary comment lines in model properties

### 📚 Documentation

- *(changelog)* Regenerate with the corrected git-cliff configuration

### 🎨 Styling

- Format with the application's Pint configuration

### ⚙️ Miscellaneous Tasks

- The module carries functionality, not the toolchain

## [1.0.1] - 2026-09-09

### 🚜 Refactor

- *(mes)* Update permission handling in DevMESDatabaseSeeder
- *(mes)* Declare domain permissions instead of seeding a private list

### ⚙️ Miscellaneous Tasks

- *(models)* Remove unnecessary comment lines in model docblocks

## [1.0.0] - 2026-08-25

### 🚀 Features

- *(mes)* Refactor MES module structure and add stock movement functionality
- *(config)* Clean up configuration file and add runtime setting definitions
- *(mes)* Introduce WorkCenter and WorkCenterCalendar models with associated functionality
- *(mes)* Add initial Swagger documentation and ItemTracingType tests
- *(mes)* Add production order migrations and configuration checks
- *(mes)* Enhance MESTables enum with utility traits
- *(mes)* Routing and routing operations (Task 5)
- *(mes)* Bill of materials and multi-level explosion (Task 4)
- *(mes)* Production order lifecycle with immutable snapshots (Task 6)
- *(mes)* Production order operation execution (Task 7)
- *(mes)* Material backflush on operation completion (Task 8)
- *(mes)* Lot and serial traceability (Task 9)
- *(mes)* Quality checks and non-conformance handling (Task 10)
- *(mes)* Work-center capacity and scheduling service (Task 11)
- *(mes)* Machine downtime and OEE calculation (Task 12)
- *(mes)* Shifts, shift instances and operator logging (Task 13)
- *(mes)* HTTP domain actions via Core registry and policy (Task 14)
- *(mes)* Production dashboard widget (Task 15)
- *(mes)* Manual material consumption (deferred Task 8 follow-up)
- *(mes)* Filament resources for core entities (Task 15 follow-up)
- *(mes)* Filament resources for quality, downtime and shifts (Task 15 complete)
- *(mes)* Auto-create production orders from confirmed sales orders
- *(mes)* Auto quality checks from plans and stock-shortage detection
- *(mes)* Partial consumption on stock shortage
- *(mes)* Notify recipients on material shortage

### 🐛 Bug Fixes

- *(tests)* Add TestCase class for AI tests in MES module
- *(mes)* Cap Company slug length in factory and test helpers
- *(mes)* Green test suite on PHP 8.5 (is_deleted column + factory states)
- *(mes)* RoutingOperation factory state as array (PHP 8.5 binding)

### 🚜 Refactor

- *(tests)* Update module dependency tests and refine stock movement assertions
- *(mes)* Update WorkCenter model to enhance factory and scope functionality
- *(mes)* Seed definitions via SeedReconciler, uniform column set
- *(mes)* Run aggregate transactions on the aggregate root connection

### 📚 Documentation

- *(mdc)* Add MES module context rules for ERP integration and operational guidelines
- Fix glossary cross-references
- Update glossary and module context with new production order and backflush details
- *(mes)* Developer RAG reference and simple user guide (Task 17)
- *(mes)* RAG user and developer docs for the new capabilities

### 🧪 Testing

- *(stock)* Enhance StockMovementData tests for immutability and DateTime handling
- *(mes)* End-to-end production cycle and snapshot invariant (Task 16)
- *(mes)* Add dev seeder for the MES SPA with demo data, roles and users

### ⚙️ Miscellaneous Tasks

- *(tests)* Update PHPUnit configuration and enhance test suite structure
- *(mes)* Bump version to v1.0.1
- *(mes)* Normalize PHPDoc spacing in WorkCenter model
- *(mes)* Mark module as laraplate_owned
- Correct version bump to v1.1.0
- Add versioning scripts and setup hooks to composer.json

<!-- generated by git-cliff -->

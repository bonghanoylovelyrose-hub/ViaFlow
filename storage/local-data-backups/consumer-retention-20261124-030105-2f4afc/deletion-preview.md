# Local consumer account deletion preview

No deletion has been executed. Explicit confirmation is required.

Database `water_billing_systems` on `127.0.0.1:3306` in the Laravel `local` environment.
Database backup `water_billing_systems.sql` contains all 36 tables and their data. SHA-256 `713c6660060d6252edf99721234e799c4fa93f66ff502cc1b44230824aee2de0`.
Deletion scope SHA-256 `10e1109a2f9c02a8975b1098ccf99b7599decc9069da8ec08301a29ccdcc2ee3`.

## Accounts to retain

| Meter number | Consumer ID | Status |
| --- | ---: | --- |
| MT-8989 | 75 | Disconnected |
| MT-2121 | 76 | Active |
| MT-3333 | 109 | Active |
| MT-7777 | 80 | Active |
| MT-0909 | 88 | Active |
| MT-9191 | 87 | Active |

## Accounts to remove

| Consumer ID | Meter number | Status | Rows to remove |
| ---: | --- | --- | ---: |
| 57 | MT3232 | Active | 43 |
| 77 | Unassigned | Rejected | 4 |
| 78 | MT-4444 | Active | 39 |
| 79 | MT-5674 | Active | 33 |
| 81 | MT-7878 | Active | 72 |
| 82 | MT-1111 | For Review | 24 |
| 83 | MT-9999 | Active | 34 |
| 84 | MT-1212 | For Review | 30 |
| 85 | MT-1313 | For Review | 28 |
| 86 | MT-8181 | Active | 44 |
| 106 | MT-7676 | Active | 22 |
| 107 | MT-2525 | Active | 36 |
| 108 | MT-0808 | Active | 28 |
| 110 | Unassigned | Pending Verification | 6 |

## Rows to remove

| Table | Remove | Preserve |
| --- | ---: | ---: |
| announcement_deliveries | 1 | 2 |
| bills | 40 | 10 |
| consumers | 14 | 6 |
| consumer_notification_logs | 50 | 21 |
| consumer_password_setups | 10 | 5 |
| consumer_reconnections | 6 | 1 |
| disconnection_notices | 5 | 3 |
| incidents | 9 | 5 |
| installation_reschedule_requests | 4 | 2 |
| jobs | 1 | 2 |
| loans | 12 | 5 |
| loan_agreements | 5 | 3 |
| loan_dss_evaluations | 13 | 5 |
| loan_installments | 30 | 30 |
| loan_repayments | 4 | 1 |
| loan_repayment_allocations | 15 | 2 |
| meter_assignments | 17 | 10 |
| meter_readings | 29 | 4 |
| notifications | 125 | 54 |
| payments | 28 | 5 |
| service_requests | 13 | 6 |
| users | 12 | 12 |
| **Total removed** | **443** | |

Notification deletions include 67 staff-facing copies tied to the affected records. Staff login rows, retained consumer logins, settings, rates, and the shared announcement are preserved.

## Uploaded files

51 existing files are referenced exclusively by affected records. All were copied and checksum-verified in `consumer-uploads`.
0 exclusive document paths are already missing from both upload disks.
0 document paths shared with preserved records are protected.

The foreign key audit found no preserved rows depending on rows marked for deletion.

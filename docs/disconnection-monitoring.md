# Disconnection monitoring

The daily `billing:monitor-overdue` command runs at 06:00 through the Laravel scheduler. Keep the scheduler running to dispatch overdue reminders and formal disconnection notices. Opening **Active Accounts** also reconciles account standing without sending email.

- Three consecutive unpaid calendar months qualify after each bill's due date and grace period. Missing or settled months break the streak; multiple bills in one month count once.
- Carried-forward debt retains its original billing months. Verified payments clear the oldest amounts first. Pending payments do not settle debt, and carried balances are never added to the outstanding total twice.
- A qualifying connected account appears in **Disconnection Records** and stays Active. The notice records its issue time, outstanding balance, triggering bill, and three-day payment deadline. Delayed successful email delivery extends the deadline to allow three full days from dispatch; repeat dispatches do not reset it.
- Full verified payment closes the active notice automatically. The notice remains in `disconnection_notices` with status `Settled` and a settlement timestamp.
- Only an Admin can confirm physical disconnection after the payment deadline. Confirmation checks the current balance again, records the confirming Admin and disconnection time, and moves the account to Inactive Accounts with status `Disconnected`.
- Monitoring and confirmation lock the consumer record while reconciling standing. Existing active notices are reused, and the unique consumer/triggering-bill key prevents duplicate notices for the same billing cycle. Email failures are logged without undoing account standing.

## Disconnection Records interface

The Active Accounts page in Consumer Management displays two independent tables. **Disconnection Notice** appears first and contains accounts with notice history, including settled notices. It shows payment status, outstanding balance, notice date, and the full three-day grace period. **Active Accounts** appears below and contains only accounts with stored status **Active** and no notice records. An account appears once, in only one table, even when it has multiple notice cycles. For Review, inactive, disconnected, and for-reconnection accounts are excluded from both tables.

Each table has its own automatic search and membership filters. The notice table additionally has **All Status**, **Paid Accounts**, **Unpaid Accounts**, and **Ready for Disconnection** filters based on actual verified payments. Changing or clearing one table's filters preserves the other table's filters and page. Pagination uses `notice_page` for notices and `page` for ordinary active accounts. Fee columns, amounts, and assessment controls are not displayed on this page; fee management in Inactive Accounts and fee calculations in Payments remain unchanged.

An unpaid notice stays neutral throughout its three-day window. At the exact deadline, an unpaid connected account becomes **Ready for Disconnection** and its row turns red. Its actual account status stays Active until the Admin confirms physical disconnection. Settled and disconnected notices remain available under each account's expandable notice history, with one row per consumer across all notice cycles.

## Inactive consumer access

Consumers keep their existing meter-number login and password after disconnection. Login opens an inactive account summary and notification popup with their account details, actual outstanding balance, and total to pay. Bills, statements, payments, receipts, profile/password settings, and reconnection status remain accessible. Dashboard, loans, incidents, and announcements are blocked on the server while service is disconnected.

Reconnection fees have been removed. There is no fee column or assessment form in Inactive Accounts, and opening Payments never creates a reconnection charge. New positive fee assessments are rejected. Migration `2026_11_05_000004_remove_reconnection_fee_charges` credits only unpaid reconnection-fee portions using `bills.waived_reconnection_fee_amount`, including fees carried into newer bills. Original bill amounts, paid fees, payment records, disconnection history, and existing activation history remain intact; water debt is not waived. The credit is included in outstanding-balance calculations and repeated cleanup cannot credit a fee twice.

Consumers are instructed to visit the office and settle the full required payment. Paying their bills or receiving service-request approval/completion alone does not restore normal access. Access follows `ConsumerStatusService` on every request, including for consumers already logged in.

## Admin reconnection

1. Check the **Outstanding Balance** column in Inactive Accounts. A positive balance disables **Reconnect** and displays the remaining amount; office payments are recorded and verified in **Payments**.
2. A zero balance enables **Reconnect** without requiring a fee assessment or a new payment record. Balances come from actual bill amounts, verified payments, and recorded fee waivers. Pending payments and manually cached Paid labels cannot clear an outstanding amount.
3. Open **Reconnect** to review consumer name, account number, meter number, inactive status, and outstanding balance. Enter **Reconnection Date** and **Reconnection Date and Time**, then choose **Activate Account** and confirm the prompt. The server locks and rechecks the current account balance before activation. Confirmation marks the account Active and Connected and moves it to Active Accounts. The Edit Consumer form's former Renewal Date edits a separate reconnection date; membership dates remain intact.
4. After the activation transaction commits, the consumer automatically receives a professional activation email with their name, account/meter details, registered email, reconnection schedule, login URL, meter-number username instructions, a secure password setup/reset link, rescheduling link, and customer support contacts. SMTP failure is logged and shown to the Admin without rolling back activation.

Every activation retains a `consumer_reconnections` record with the confirming Admin, disconnection cycle, dates, zero reconnection charge, and any verified payment IDs. Reconnection history is expandable in Disconnection Records; all existing payment and disconnection records are preserved. Repeated confirmation cannot create another activation or email for an already-active account. Initial application activation and ordinary profile edits cannot bypass the zero-balance reconnection gate.

## Activation email and password security

Activation emails use `consumer_reconnected_{activation_id}` delivery events in `consumer_notification_logs`. A `sent` status means the configured mailer accepted the send; `failed` records a send error and leaves activation intact. Repeated activation confirmation does not send duplicate messages. Emails are deferred until database commit and never sent for rolled-back activations.

No activation, credential resend, or password recovery email contains a password. Existing passwords remain unchanged until the consumer submits a new password through the secure form. Setup links are temporary signed URLs, expire after `auth.passwords.users.expire` minutes (60 by default), and can be used once. Only a token hash is stored in `consumer_password_setups`. Tokens are scoped to the user and consumer, not just an email address, so accounts sharing a registered email remain separate. Issuing a new link invalidates the earlier link; changing the registered recipient also invalidates an outstanding link. Passwords are hashed and remember tokens rotate after successful setup.

Configure `SUPPORT_EMAIL`, `SUPPORT_PHONE`, and optionally `SUPPORT_OFFICE` for customer-service contacts. Until email/phone values are supplied, the template displays the configured Via Vida office location without inventing contact details.

## Reconnection rescheduling

The activation email's **Request for Reschedule** button opens a signed link that expires after seven days. Consumers can view the currently confirmed reconnection schedule, select a preferred future date/time, and provide a reason. Form submission uses a separate one-hour signed action plus CSRF protection. Logged-in owners and Admins can also access the current record; other consumers, tampered links, expired links, and obsolete reconnection cycles are rejected.

Requests use the `reconnection` type in `installation_reschedule_requests` and retain their activation ID, original schedule, preferred date/time, reason, and review history. A consumer-record lock prevents duplicate pending requests. Each submission creates an in-app notification for every Admin and appears in **Pending Accounts → Reschedule Requests**, where the preferred schedule is displayed and prefilled for review.

Only an Admin can **Assign New Schedule**. Approve/Reject controls have been removed. Assignment requires a future date and time and updates `reconnection_date`, `reconnection_scheduled_at`, `installation_date`, and `installation_time` atomically. The original activation record remains unchanged. The legacy approve endpoint forwards through the same assignment validation; the retired reject endpoint returns HTTP 410 without changing a request or schedule. An old activation's request cannot change a newer reconnection schedule.

## Three-reschedule limit

Each consumer may use at most three installation reschedules in total, shared across connection, loan-installation, and reconnection requests. Initial installation schedules do not consume attempts. Pending requests and rejected requests without an assigned schedule do not consume attempts. A successful assignment saves an `assignment_number` and `assigned_at` on its existing request. Usage is derived from this database history rather than a separately editable counter.

Migration `2026_11_06_000001_track_installation_reschedule_assignments` backfills existing records with saved new installation dates, preserving every schedule and request. Historical usage is retained even if an older account has already exceeded three reschedules; no further assignment is permitted. A new nullable consumer `installation_time` field retains assigned times alongside existing installation dates.

Both portals display usage and remaining attempts, such as **2 of 3 used**. At three, consumer reschedule buttons and forms are disabled. Direct POST requests and Admin assignment endpoints also enforce the limit. Assignments lock the consumer and request in a transaction, and a unique consumer/assignment-number constraint prevents duplicate numbering. Repeated submission, invalid or unchanged schedules, unauthorized actions, and rolled-back assignments do not consume attempts.

Every successful assignment automatically sends the updated date/time after commit. `consumer_notification_logs` records `installation_reschedule_{request_id}_assigned` with sent/failed status. A failed email does not undo the saved schedule or add another attempt on retry. Each email includes: "Please be reminded that you are allowed a maximum of 3 installation reschedules." The third assignment prominently includes: "IMPORTANT: This is your final reschedule. No further rescheduling will be allowed. Please ensure that someone is available at home on the scheduled installation date." The final schedule email does not offer another rescheduling link.

Run the focused regression checks with:

```powershell
C:\xampp\php\php.exe artisan test --filter="Disconnection|OverdueToReconnection"
```

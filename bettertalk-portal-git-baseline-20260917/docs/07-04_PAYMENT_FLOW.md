# Better Talk — Payment Flow

## 1. V1 Operating Model

Agents manage payment requests and confirmation manually through the portal. Automated gateway reconciliation may be added later.

Current/considered collection channels include Better Talk business bank accounts and merchant/payment arrangements such as JazzCash and Easypaisa. Exact production methods and gateway contracts must be confirmed before activation.

## 2. Standard Flow

| Stage | Action | Portal Status |
|---|---|---|
| Intake complete | Agent creates payment request | `Payment Pending` |
| Link/details sent | Save channel, amount, reference, sent time | `Payment Pending` |
| Client pays | Receive provider reference/proof | `Verification Pending` |
| Agent verifies | Check merchant/bank record; upload proof | `Paid` |
| Appointment booked | Link payment to appointment | `Paid / Allocated` |
| Failure/expiry | Record reason | `Failed` or `Expired` |
| Refund | Record amount, reason, approver, reference | `Refunded` or `Partially Refunded` |

## 3. Payment Record

Required fields:

- Payment ID and public payment reference.
- Client ID, lead ID, originating Call ID, and appointment ID when available.
- Requested and received amount, currency, and service/offer.
- Payment method/provider.
- Provider transaction/reference ID.
- Proof/screenshot where required.
- Status, created/sent/paid/verified timestamps.
- Creating and verifying users.
- Refund details and audit history.

## 4. Channel Rules

- **WhatsApp-origin client:** Send payment link/details in the same customer-initiated WhatsApp conversation while the service window is active.
- **IVR-origin client:** Send payment link by SMS. A separate `wa.me` link may be sent so the client—not Better Talk—initiates WhatsApp.
- Do not mark payment `Paid` solely because a screenshot was received; verify against the merchant/bank record where possible.
- Prevent two appointments from accidentally consuming the same payment unless an authorized adjustment is recorded.

## 5. Identity and Authorization

- The payer may differ from the client; store payer name/last identifying details only as legitimately supplied and needed.
- Phone-number matching does not prove wallet/account ownership.
- Payment must be authorized through the provider's own secure process (OTP, PIN, app approval, 3-D Secure, or equivalent).
- Better Talk staff must never request or store a client's PIN, OTP, CVV, or complete card credentials.
- An IVR payment flow must clearly identify amount and merchant and require deliberate payer authorization.

## 6. Reconciliation and Exceptions

- Search by Payment ID, provider reference, Client ID, Call ID, or appointment.
- Flag amount mismatch, duplicate reference, pending/unclear proof, chargeback, refund, and expired link.
- Admin can correct statuses with a mandatory reason and audit trail.
- Agent permissions for refunds or manual overrides should be restricted.

## 7. Pending Business Decisions

- Final V1 price/discount to configure in production.
- Final enabled gateway/payment methods and fees.
- Who may approve refunds and payment overrides.
- Payment-link expiry and reuse rules.
- Whether provider webhooks are included in V1 or a later phase.


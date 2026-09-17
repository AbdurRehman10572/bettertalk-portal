# Better Talk — IVR / Direct Call Flow

## 1. Purpose

The incoming-call route must let an agent understand the client's need, record the interaction, arrange payment, and schedule a doctor without the agent remaining on the final doctor-client call.

## 2. Incoming Call Journey

| Step | System/Person | Action |
|---|---|---|
| 1 | Client | Calls Better Talk business number |
| 2 | IVR/call platform | Creates unique Call ID and routes call |
| 3 | Portal integration | Searches phone; finds/creates Client ID |
| 4 | Agent | Answers, understands need, and records intake |
| 5 | Agent/System | Sends payment gateway link by SMS |
| 6 | Agent/System | Sends `wa.me` link by SMS so client can initiate WhatsApp if needed |
| 7 | Client | Completes payment and/or starts WhatsApp |
| 8 | Agent | Confirms payment and schedules doctor |
| 9 | Doctor | Calls client directly at scheduled time using portal access |
| 10 | Portal | Stores appointment, doctor call ID, outcome, and history |

## 3. Call ID Rules

- Generate a unique Call ID at call start.
- Store caller number, direction, queue/route, answering agent, start/end times, duration, disposition, notes, and recording reference where enabled.
- Link the Call ID to Client ID and Lead ID.
- Include a traceable reference when generating the payment request; do not expose sensitive internal information in the public link.
- A later doctor call receives a new Call ID but links to the same Client ID and appointment.
- Multiple calls must never overwrite earlier call records.

## 4. Payment Link Reference

The payment request must contain a unique token/reference. The portal should map it to:

`Client ID + Lead/Appointment ID + originating Call ID + requested amount + expiry/status`

The client-facing link should not reveal raw sequential identifiers if that creates a privacy or enumeration risk.

## 5. Returning Client

When a client returns months later:

1. Search by caller number and display possible match.
2. Agent verifies identity using safe business-approved information.
3. Open the existing Client ID rather than creating a duplicate when confidently matched.
4. Show prior doctors, appointments, payment history, and permitted notes.
5. Create a new lead/interaction and Call ID.
6. Offer the same doctor when requested, appropriate, and available.

## 6. Vendor Requirements

- One business number with IVR/queue routing.
- Agent and doctor user access.
- Unique Call IDs and call-detail records.
- Scheduled doctor calling without agent bridging.
- Role-based access and call masking where supported/required.
- Call recording with legally appropriate announcement/consent configuration.
- Webhook/API or reliable export for call events and recordings.
- Call outcome/disposition and agent notes.
- Failed-call, no-answer, retry, and missed-call handling.
- Provider must explain number ownership, SMS capability, WhatsApp compatibility, retention, security, uptime, and charges.

## 7. Important Clarification

The IVR does not normally know the payer's bank/wallet balance in advance. Payment authorization occurs through the selected bank/wallet/card process. The system must not assume that the caller owns or is authorized to use a wallet merely because the phone number matches.


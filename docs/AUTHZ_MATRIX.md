# Authorization Matrix

Source review at branch snapshot cc161bc12ce8824d0f1abeb68dbf0cf4fcb7945e. Static review only, not penetration testing. 401 unauthenticated, 403 role denied, 404 object outside owner scope.

| Endpoint | Patient | Partner | Ops admin | Reviewer | Sysadmin | Scope |
|---|---|---|---|---|---|---|
| GET /services,/areas | Allow | Allow | Allow | Allow | Allow | Public catalogue |
| POST /auth/register,/login,/forgot-password,/reset-password | Public | Public | Public | Public | Public | Rate limited |
| GET /me, POST /auth/logout, DELETE /me | Allow | Allow | Allow | Allow | Allow | Authenticated; staff self-delete refused |
| GET /bookings and detail | Own | Active assignment | Global | Deny | Global | Controller scopes data |
| POST /bookings, cancel | Allow | Deny | Deny | Deny | Deny | Patient only; create now separately rate limited |
| GET /coverage | Allow | Deny | Deny | Deny | Deny | Patient only |
| POST/GET /complaints, detail | Own | Own related booking | Deny | Deny | Deny | Reporter-bound |
| Admin complaint read | Deny | Deny | Allow | Allow | Allow | View audited |
| Admin complaint transitions | Deny | Deny | Allow | Allow | Deny | Ops/reviewer |
| Admin partner read/docs/review/checks/correction | Deny | Deny | Deny | Allow | Allow | Private disk; access audit |
| Admin partner approve/reject/activate/suspend | Deny | Deny | Deny | Deny | Allow | Sysadmin only |
| Admin booking screen/assign/cancel | Deny | Deny | Allow | Deny | Deny | Ops admin |
| /midwife/* | Deny | Allow | Deny | Deny | Deny | Partner profile from authenticated user; booking actions require active assignment |

## Gaps
- Admin document endpoint is authenticated direct download, not a temporary signed URL.
- /admin Blade shell exists; browser token storage, CSRF and full action UX are not E2E-tested.
- Partner status vocabulary includes DRAFT, SUBMITTED, IN_REVIEW, NEEDS_CORRECTION, ACTIVE, REJECTED, EXPIRED and SUSPENDED. Do not relabel ACTIVE as VERIFIED.
- Partner booking list uses active assignments; historical access policy needs a test.
- Ops/sysadmin booking visibility is broad; review least privilege.
- Expand IDOR tests for every route with a model identifier.

Tests observed: BookingFlowTest role/cross-account checks; PartnerApiHardeningTest isolation and upload; ComplaintTest reporter/admin flow; PartnerVerificationTest reviewer/sysadmin workflow.

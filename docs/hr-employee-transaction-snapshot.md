# HR Employee Authority Snapshot Concept
Future transaction tables can store immutable authority snapshot fields:
- created_by_name_snapshot
- created_by_position_snapshot
- approved_by_name_snapshot
- approved_by_position_snapshot
- authority_employee_id
- authority_name_snapshot
- authority_position_snapshot
- authority_license_type_snapshot
- authority_license_number_snapshot
- authority_license_expired_snapshot

Use `App\\Services\\EmployeeAuthorityService` as source-of-truth resolver.

# 4.4.2 Data Dictionary

A data dictionary provides detailed information about the structure and attributes of data stored within a system database. It includes the table name, main attributes, data type, key constraints, and a short description of the data. In the Cattle Management System, the data dictionary helps developers and future maintainers understand how livestock records, user access, workflow approvals, reports, and operational data are stored consistently.

The proposed system contains several modules such as user management, cattle directory, calving, mortality, health treatment, transfer document, pasture, feeding, inventory, driver delivery, task notification, and audit log. To keep the data dictionary concise and suitable for Chapter 4, related tables are grouped by module and only the most important attributes are listed.

## Table 4.6: User and Access Control Data Dictionary

| No | Table | Main Attributes | Data Type / Size | Key | Description |
|---|---|---|---|---|---|
| 1 | users | id, name, email, password, role, status, profile_photo, last_login_at, created_at, updated_at | BIGINT(20), VARCHAR(255), TIMESTAMP | PK | Stores registered user accounts, login details, role, status, and profile information. |
| 2 | permissions | id, user_id, module, permission, created_at, updated_at | BIGINT(20), VARCHAR(255), TEXT, TIMESTAMP | PK, FK | Stores each user's module access permission such as view, create, edit, delete, full, or no access. |
| 3 | field_level_permissions | id, module, field_name, role, permission, created_at, updated_at | BIGINT(20), VARCHAR(255), TIMESTAMP | PK | Stores field-level access rules for specific modules and user roles. |
| 4 | workflow_assignments | id, module, assignments, created_at, updated_at | BIGINT(20), VARCHAR(255), JSON, TIMESTAMP | PK | Stores generic workflow assignee settings for approval-based modules. |
| 5 | treatment_workflow_assignments | id, prepared_by_user_ids, checked_by_user_ids, approved_by_user_ids, created_at, updated_at | BIGINT(20), JSON, TIMESTAMP | PK, FK | Stores assigned users for treatment workflow approval steps. |
| 6 | calving_workflow_assignments | id, issued_by_user_ids, verified_by_user_ids, checked_by_user_ids, witnessed_by_user_ids, approved_by_user_ids | BIGINT(20), JSON | PK, FK | Stores assigned users for calving and calving checklist workflow steps. |
| 7 | transfer_workflow_assignments | id, issued_by_user_ids, approved_by_user_ids, transported_by_user_ids, received_by_user_ids, completed_by_user_ids | BIGINT(20), JSON | PK, FK | Stores assigned users for CTV, Receival, and SIV transfer workflow steps. |

## Table 4.7: Cattle and Daily Operation Data Dictionary

| No | Table | Main Attributes | Data Type / Size | Key | Description |
|---|---|---|---|---|---|
| 1 | cattle | id, tag_no, lcc_running_number, category, birth_date, gender, operating_unit, location_block, location_phase, status, profile_picture | BIGINT(20), VARCHAR(255), DATE, DECIMAL, TIMESTAMP | PK | Stores the main cattle profile including tag number, category, birth details, location, and status. |
| 2 | cattle_custom_fields | id, field_type, value, is_active, sort_order, created_at, updated_at | BIGINT(20), VARCHAR(255), BOOLEAN, INT | PK | Stores selectable cattle field values such as category, coat colour, condition, and ownership. |
| 3 | cattle_breeding_records | id, cattle_id, breeding_date, breeding_method, bull_tag, expected_calving_date, calf_tag, remarks | BIGINT(20), DATE, VARCHAR(255), DECIMAL, TEXT | PK, FK | Stores breeding history linked to cattle records. |
| 4 | cattle_health_records | id, cattle_id, source_type, source_id, reference_no, date, treatment, dosage, status, metadata | BIGINT(20), VARCHAR(255), DATE, TEXT, JSON | PK, FK | Stores summarized health records generated from treatment, calving, or mortality sources. |
| 5 | weekly_cattle_return_workflows | id, period_from, period_to, operating_unit, endorsement_step, status, is_completed, completed_at | BIGINT(20), DATE, VARCHAR(255), JSON, TIMESTAMP | PK | Stores weekly cattle return workflow details and endorsement progress. |
| 6 | daily_operation_entries | id, estate_name, month, year, category_code, daily_values, missing, remark | BIGINT(20), VARCHAR(255), INT, JSON, TEXT | PK | Stores daily operation livestock count and missing record values by estate and month. |
| 7 | daily_operation_duty_persons | id, estate_name, month, year, name, position, created_at, updated_at | BIGINT(20), VARCHAR(255), INT, TIMESTAMP | PK | Stores duty person details for daily operation reporting. |
| 8 | feeding_records | id, date, trip_no, cattle_count, feed_type, planned, actual_usage, receive, carry_forward, balance, remarks | BIGINT(20), DATE, INT, VARCHAR(255), DECIMAL, TEXT | PK | Stores daily cattle feeding activity, usage, received quantity, and balance. |
| 9 | feeding_options | id, field_type, value, sort_order, created_at, updated_at | BIGINT(20), VARCHAR(255), INT, TIMESTAMP | PK | Stores configurable feeding options such as trip number and feed type. |

## Table 4.8: Calving, Mortality, Health, and Transfer Data Dictionary

| No | Table | Main Attributes | Data Type / Size | Key | Description |
|---|---|---|---|---|---|
| 1 | calving_records | id, tag_no, calving_date, sex, colour, dam_tag_no, sire_tag_no, operating_unit, status, endorsement_documents | BIGINT(20), VARCHAR(255), DATE, JSON, TIMESTAMP | PK | Stores calf birth records, parent details, location, status, and endorsement documents. |
| 2 | calving_checklists | id, month_year, operating_unit, calving_date, tag_no, treatment_iodine, colostrum_feeding_24h, workflow_status | BIGINT(20), VARCHAR(255), DATE, BOOLEAN, JSON | PK | Stores calving checklist records and monthly checklist workflow status. |
| 3 | calving_monthly_workflows | id, month_year, operating_unit, endorsement_step, status, is_completed, completed_at | BIGINT(20), VARCHAR(255), JSON, TIMESTAMP | PK | Stores monthly calving checklist endorsement progress. |
| 4 | mortality_cases | id, cattle_id, lmc_no, category, death_date, cause_of_death, status, current_step, endorsement_documents | BIGINT(20), VARCHAR(255), DATE, TEXT, JSON | PK, FK | Stores cattle mortality reports, death cause, PM status, and approval workflow state. |
| 5 | postmortem_examinations | id, mortality_case_id, organ_findings, preliminary_cause, final_cause, created_at, updated_at | BIGINT(20), TEXT, TIMESTAMP | PK, FK | Stores post-mortem examination findings for mortality cases. |
| 6 | mortality_approvals | id, mortality_case_id, user_id, step, action, comments, created_at | BIGINT(20), VARCHAR(255), TEXT, TIMESTAMP | PK, FK | Stores mortality workflow approval actions and comments. |
| 7 | treatments | id, treatment_no, cattle_id, tag_no, date, symptoms, treatment_code, dosage, follow_up_required, status | BIGINT(20), VARCHAR(255), DATE, TEXT, BOOLEAN | PK, FK | Stores cattle treatment records, symptoms, dosage, follow-up, and workflow status. |
| 8 | treatment_codes | id, code, label, description, is_active, created_at, updated_at | BIGINT(20), VARCHAR(10), VARCHAR(255), TEXT, BOOLEAN | PK | Stores predefined treatment codes used in health records. |
| 9 | treatment_monthly_workflows | id, year, month, operating_unit, endorsement_step, status, is_completed, completed_at | BIGINT(20), INT, VARCHAR(255), JSON, TIMESTAMP | PK | Stores monthly treatment endorsement workflow information. |
| 10 | veterinary_contacts | id, name, type, position, organization, phone, email, address, emergency, rating | BIGINT(20), VARCHAR(255), TEXT, BOOLEAN, INT | PK | Stores veterinarian, clinic, and supplier contact information. |
| 11 | transfer_documents | id, document_no, type, from_location, to_location, date, vehicle_no, driver_name, status, current_step | BIGINT(20), VARCHAR(255), DATE, DECIMAL, JSON | PK | Stores CTV, Receival, and SIV transfer document header details and workflow status. |
| 12 | transfer_livestocks | id, transfer_document_id, tag_no, category, quantity, value, remarks | BIGINT(20), VARCHAR(255), INT, DECIMAL, TEXT | PK, FK | Stores cattle or livestock items included in a transfer document. |
| 13 | transfer_approvals | id, transfer_document_id, approver_id, step, action, comments, created_at | BIGINT(20), VARCHAR(255), BOOLEAN, TEXT, TIMESTAMP | PK, FK | Stores approval actions for each transfer workflow step. |
| 14 | document_endorsements | id, mortality_case_id, category, file_path, signed_file_path, status, created_at, updated_at | BIGINT(20), VARCHAR(255), TEXT, TIMESTAMP | PK, FK | Stores endorsement document records and uploaded signed document paths. |

## Table 4.9: Pasture, Inventory, Driver, Task, and Audit Data Dictionary

| No | Table | Main Attributes | Data Type / Size | Key | Description |
|---|---|---|---|---|---|
| 1 | estates | id, name, area, latitude, longitude, place_name, is_active, created_at, updated_at | BIGINT(20), VARCHAR(255), DECIMAL, BOOLEAN | PK | Stores estate or operating unit information and map coordinates. |
| 2 | herds | id, name, description, created_at, updated_at | BIGINT(20), VARCHAR(255), TEXT, TIMESTAMP | PK | Stores herd reference records used by pasture and grazing modules. |
| 3 | pasture_blocks | id, estate_id, name, area, created_at, updated_at | BIGINT(20), VARCHAR(255), DECIMAL, TIMESTAMP | PK, FK | Stores pasture block information under an estate. |
| 4 | pasture_phases | id, pasture_block_id, name, area, created_at, updated_at | BIGINT(20), VARCHAR(255), DECIMAL, TIMESTAMP | PK, FK | Stores pasture phase information under a pasture block. |
| 5 | grazing_data | id, estate_id, herd, month, allocated_area, rotation_period, rate_per_ha, ytd_claim | BIGINT(20), VARCHAR(255), DECIMAL, INT | PK, FK | Stores monthly grazing area, rotation, rate, and claim calculation data. |
| 6 | grazing_blocks | id, grazing_data_id, block_id, area, actual, achievement, rate, created_at, updated_at | BIGINT(20), VARCHAR(255), DECIMAL, TIMESTAMP | PK, FK | Stores detailed block-level grazing achievement and rate data. |
| 7 | medications | id, name, generic, category, batch_number, batch_qty, stock, receival_date, expiry_date, status | BIGINT(20), VARCHAR(255), INT, DATE, JSON | PK | Stores medicine inventory item details and stock quantity. |
| 8 | medication_histories | id, medication_id, action, quantity, remarks, created_at | BIGINT(20), VARCHAR(255), INT, TEXT, TIMESTAMP | PK, FK | Stores medication stock movement history. |
| 9 | stocks | id, stockable_type, stockable_id, supplier_id, min_threshold, safety_stock, oso_avg, lead_time | BIGINT(20), VARCHAR(255), INT, FLOAT | PK, FK | Stores stock control settings for medication inventory. |
| 10 | stock_histories | id, stock_id, user, action, detail, created_at, updated_at | BIGINT(20), VARCHAR(255), TEXT, TIMESTAMP | PK, FK | Stores stock adjustment and movement history. |
| 11 | suppliers | id, name, type, contact, phone, email, address, created_at, updated_at | BIGINT(20), VARCHAR(255), TEXT, TIMESTAMP | PK | Stores supplier contact information for inventory management. |
| 12 | driver_profiles | id, user_id, phone, license_number, license_expiry, vehicle_assigned, emergency_contact, total_deliveries | BIGINT(20), VARCHAR(255), DATE, INT, TEXT | PK, FK | Stores driver-specific profile details linked to user accounts. |
| 13 | delivery_records | id, delivery_number, date, time, user_id, vehicle, origin, destination, cargo_type, status | BIGINT(20), VARCHAR(255), DATE, TIME, TEXT | PK, FK | Stores delivery history, route, cargo, and driver information. |
| 14 | tasks | id, title, description, status, priority, category, due_date, assignee_id, created_by | BIGINT(20), VARCHAR(255), TEXT, DATE | PK, FK | Stores task records assigned to system users. |
| 15 | task_notifications | id, type, title, message, user_id, is_read, created_by, created_at | BIGINT(20), VARCHAR(255), TEXT, BOOLEAN, TIMESTAMP | PK, FK | Stores workflow and task notifications for users. |
| 16 | audit_logs | id, user_id, event, auditable_type, auditable_id, route_name, method, url, ip_address, created_at | BIGINT(20), VARCHAR(255), TEXT, TIMESTAMP | PK, FK | Stores audit trail for create, update, and delete actions in the system. |

Overall, these tables support the complete operation of the Cattle Management System. The user and permission tables control access, the cattle and operation tables store livestock records, the workflow tables manage endorsement processes, and the support tables store reporting, notification, inventory, delivery, and audit information.

# Calving Module

## Overview

The Calving Module is a comprehensive system for managing and tracking calving records for SAWIT KINABALU FARM PRODUCTS SDN BHD. It follows the same design patterns and workflow as the existing mortality module, providing a complete solution for recording, managing, and approving calving events.

## Features

### 1. Calving Record Management
- **Create Calving Records**: Add new calving events with comprehensive details
- **View Records**: Detailed view of all calving records
- **Edit Records**: Modify existing calving information
- **Delete Records**: Remove calving records (with appropriate permissions)

### 2. Calving Details Tracked
- Company Information (Name, MCC No., Operating Unit)
- Calving Date and Week
- Calf Tag Number
- Sex (MC = Male Calf, FC = Female Calf)
- Colour/Markings
- Dam/Bull Tag Number
- Location (Block/Phase)
- General Condition
- Treatments (Iodine, Woundsarex)
- Colostrum Feeding (24H)
- Maminume Supplementation
- Tagging Checklist Date
- LCC Running Number

### 3. Approval Workflow
- **4-Level Approval Process**:
  1. **Issued By**: Sr. Assistant Livestock 
  2. **Verified By**: Supervisor Livestock
  3. **Witness By**: Penyelia Sekuriti
  4. **Approved By**: Act. Livestock Manager

### 4. Dashboard Features
- Monthly statistics overview
- Calving records table
- Quick actions for common tasks
- Filter by month/year, operating unit, and status
- Export to CSV functionality

## File Structure

```
cattle-project/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       └── CalvingController.php    # Main controller for calving operations
│   └── Models/
│       └── CalvingRecord.php            # Calving record model
├── database/
│   └── migrations/
│       └── 2026_01_15_000000_create_calving_records_table.php
│   └── seeders/
│       └── CalvingRecordsSeeder.php
├── routes/
│   └── web.php                          # Routes added for calving module
├── calving-pages/
│   ├── dashboard.html                   # Calving dashboard
│   ├── calving-create.html              # Create new calving record
│   └── view-calving.html                # View calving record details
└── resources/
    └── views/
        └── calving/                     # Blade views (to be created)
```

## Database Schema

### Table: `calving_records`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| company_name | string | Company name |
| company_no | string | Company registration number |
| ownership | string | Ownership information |
| form_no | string | Form number (default: FORM 1B) |
| mcc_no | string | MCC registration number |
| month_year | string | Month and year (e.g., Sept/2024) |
| operating_unit | string | Operating unit name |
| week | int | Week number of the month |
| calving_date | date | Date of calving |
| tag_no | string | Calf tag number |
| sex | string | Sex of calf (MC/FC) |
| colour | string | Colour/markings |
| dam_tag_no | string | Dam or Bull tag number |
| location_block | string | Block location |
| location_phase | string | Phase location |
| general_condition | string | Health condition |
| treatment_iodine | boolean | Iodine treatment applied |
| treatment_woundsarex | boolean | Woundsarex treatment applied |
| colostrum_feeding_24h | boolean | Colostrum fed within 24 hours |
| maminume | boolean | Maminume supplement given |
| tagging_checklist_date | date | Tagging checklist completion date |
| lcc_running_number | string | LCC running number |
| status | string | Record status |
| issued_by_* | various | Issue approval fields |
| verified_by_* | various | Verification approval fields |
| witness_by_* | various | Witness approval fields |
| approved_by_* | various | Approval fields |
| created_at, updated_at | timestamps | Laravel timestamps |

## API Endpoints

### GET Endpoints
- `GET /calving` - List all calving records
- `GET /calving/{id}` - Show a specific record
- `GET /calving/create` - Show create form
- `GET /calving/{id}/edit` - Show edit form
- `GET /api/calving/by-month` - Get records by month (API)
- `GET /calving/export` - Export records to CSV

### POST Endpoints
- `POST /calving` - Store new record
- `POST /calving/{id}/issue` - Issue record
- `POST /calving/{id}/verify` - Verify record
- `POST /calving/{id}/witness` - Witness record
- `POST /calving/{id}/approve` - Approve record
- `POST /calving/{id}/reject` - Reject record

### PUT/PATCH Endpoints
- `PUT /calving/{id}` - Update record

### DELETE Endpoints
- `DELETE /calving/{id}` - Delete record

## Status Values

- `pending` - New record, awaiting first approval
- `issued` - Issued by initial officer
- `verified` - Verified by supervisor
- `witnessed` - Witnessed by security
- `approved` - Fully approved by manager
- `rejected` - Record rejected

## Usage

### Accessing the Module

Navigate to `/calving` to access the calving dashboard.

### Creating a New Calving Record

1. Click "Record Calving" on the sidebar
2. Fill in all required fields
3. Select appropriate treatments and care options
4. Click "Save Record" or "Save & Issue" to submit

### Approval Workflow

1. **Issue**: Initial officer signs and issues the record
2. **Verify**: Supervisor verifies the record details
3. **Witness**: Security personnel witnesses the record
4. **Approve**: Manager provides final approval

### Exporting Records

1. Navigate to the dashboard
2. Filter by month/year and operating unit
3. Click "Export CSV" to download records

## Integration with Existing Modules

The Calving Module integrates with:
- **Mortality Module**: Shared sidebar and navigation
- **Cattle Module**: Calf records can be linked to parent cattle
- **Health Module**: Treatment records can be associated with calving events

## Security and Permissions

The module uses Laravel's permission system. Users need appropriate permissions to:
- View calving records
- Create new records
- Edit records
- Delete records
- Approve/reject records

## Future Enhancements

- Link calving records to dam and sire cattle records
- Generate automatic reports and statistics
- Add photo upload capability for calf identification
- Integration with weight tracking system
- SMS/email notifications for approval workflow
- Mobile app support for field data entry

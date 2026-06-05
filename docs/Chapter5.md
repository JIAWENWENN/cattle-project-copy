# CHAPTER FIVE
# SYSTEM IMPLEMENTATION AND TESTING

## 5.1 Introduction

This chapter will discuss the two primary tasks in the process of developing and completing the analysis and design for the Cattle Management System. One of the most important stages of the system development process is the implementation. In the implementation phase, each module's code is written and integrated to convert the system design into a functional system. This implementation task also includes creating the database, connecting the system to any necessary external services, and implementing the front-end and back-end components. Building a completely functional system based on the previously defined requirement and design is the goal in mind.

Another crucial task is testing, which will be executed after the implementation task is done. The function of the testing task is to ensure the system is free from any errors or bugs. The developer needs to conduct a variety of testing, such as functional testing to verify each feature works correctly, integration testing to ensure modules work together, and user acceptance testing in order to confirm the system meets user needs. Testing guarantees that the system is stable, dependable, and prepared for real world use while also assisting in the early detection of any problems.

## 5.2 Implementation

The Cattle Management System, which is built for Sawit Kinabalu Farm Products Sdn Bhd, was implemented following the several modules that were mentioned in the previous chapter. It is a web-based system that is developed by using Laravel 12 as the backend framework and also connected to the database that was managed by using MySQL. The frontend was built using Vue 3 with Inertia.js to bridge both sides, while Tailwind CSS was used for styling. Visual Studio Code served as the code editor throughout the entire development process, and Vite was used as the build tool for compiling frontend assets.

### 5.2.1 Database Connection

The Cattle Management System stores all of the data in the MySQL database named cattlesystem. The database connection is configured through the Laravel environment file, which contains the host, port, database name, username, and password. Laravel's Eloquent ORM is used throughout the system to interact with the database tables. Each module has its own set of models and migrations that define the structure of the data being stored. Figure 5.1 shows the code segment that configures the database connection for the Cattle Management System.

Figure 5.1: The code segment for Database Connection

### 5.2.2 Login Module Interface

The first thing that the user will look at when browsing the Cattle Management System is the login webpage. This webpage consists of two form input fields for the user to insert their registered email address and password as shown in Figure 5.2. Figure 5.3 shows the code segment for the login interface. The login page displays the Sawit Kinabalu logo at the top, followed by a "User Login" heading and a brief description that says "Welcome Back. Sign in to Sawit Kinabalu Cattle System." Click on the login button, and the system will recognise the user based on the inserted credentials and do the authentication by redirecting to the specific webpage based on the user's role. If the credentials are wrong, an error message will be displayed in a red box near the top of the form.

Figure 5.4 shows the main dashboard interface that the user will see after logging in. The dashboard displays four stat cards at the top showing Total Cattle, Active Cases, Health Alerts, and Mortality Rate. Below the stat cards, there is a Quick Actions section that provides direct links to commonly used functions such as adding new cattle, recording mortality, and recording treatments. Figure 5.5 and Figure 5.6 show the code segment for the login and dashboard interfaces.

Figure 5.2: The interface of the login module
Figure 5.3: The code segment for login module interface
Figure 5.4: The dashboard interface after login
Figure 5.5: The code segment for dashboard interface

### 5.2.3 Cattle Management Module Interface

The cattle management module allows the authorised user to register, track, and manage individual cattle within the estate. The main interface displays a table that lists all cattle records with their tag number, category, coat colour, location, status, and date of birth as shown in Figure 5.7. A search bar is positioned at the top of the page that lets the user filter cattle records by tag number, category, or location block. The user can also filter by category using a dropdown menu and by status to quickly narrow down the results.

To add a new cattle record, the user clicks on the "Add Cattle" button which opens a form modal as shown in Figure 5.8. The form contains several input fields including Tag No, LCC Running Number, Category, Coat Colour, Birth Date, Gender, Status, Location Block, Location Phase, Dam Tag, Dam Colour, Sire Tag, and Sire Coat Colour. The user can also upload a profile picture for the cattle. Once the form is filled and saved, the new record will appear in the cattle list. The user can also edit or delete existing records by clicking on the action buttons in the table. An export function is available that allows the user to download the cattle list as an Excel file. The code segment for the cattle management interface is shown in Figure 5.9, and the code segment for the cattle form is shown in Figure 5.10.

Figure 5.7: The cattle list interface with search and filter
Figure 5.8: The add cattle form interface
Figure 5.9: The code segment for cattle list interface
Figure 5.10: The code segment for cattle form interface

### 5.2.4 Calving Records Module Interface

The calving records module is used to record any calving event that happens in the estate. The main interface displays a list of all calving records as shown in Figure 5.11. The user can filter records by month, year, and operating unit using the filter options at the top of the page. Each record in the list shows the calf tag number, calving date, sex, dam tag, location, and the current status of the endorsement workflow.

To create a new calving record, the user clicks on the "Record Calving" button which opens the calving form as shown in Figure 5.12. The form contains sections for company information, calving details, and treatment options. The company section includes fields for company name, company number, ownership, form number, MCC number, and operating unit. The calving details section includes fields for month and year, week number, calving date, calf tag number, sex (MC for male calf or FC for female calf), colour and markings, dam or bull tag number, location block, location phase, and general condition. The treatment section includes checkboxes for iodine treatment, woundsarex treatment, colostrum feeding within 24 hours, and maminume supplementation. There is also a field for the tagging checklist date and LCC running number.

Each calving record has to go through an endorsement workflow before it is considered as fully approved. The workflow has four steps, which are Issued, Verified, Witnessed, and Approved. Each step is done by a different user based on the workflow assignment. The system tracks who signed off at each step with their name, date, and digital signature. The endorsement documents can be uploaded and downloaded at each step. After the final approval, the user can download the LCC document which serves as the official birth certificate. The code segment for the calving records interface is shown in Figure 5.13, and the code segment for the calving form is shown in Figure 5.14.

Figure 5.11: The calving records list interface
Figure 5.12: The calving record form interface
Figure 5.13: The code segment for calving records interface
Figure 5.14: The code segment for calving form interface

### 5.2.5 Mortality Records Module Interface

The mortality records module is used to record any cattle death that occurs in the estate. The main interface is divided into several sections, including Pending Approvals, History, and Completed Cases as shown in Figure 5.15. The Pending Approvals section shows all mortality records that are waiting for the next step in the endorsement workflow. The History section shows all mortality records that have been created. The Completed Cases section shows all mortality records that have been fully approved and completed.

To create a new mortality record, the user clicks on the "Record Mortality" button which opens the mortality form as shown in Figure 5.16. The form contains a cattle search field where the user can search and select the dead cattle by tag number. Once a cattle is selected, the system automatically fills in the category and location details. The user then enters the death date, death time, cause of death, preliminary cause, and any treatment that was attempted before the cattle died. There is also a section for post-mortem examination details where the user can record findings for various organs including the lungs, heart, liver, kidneys, and intestines.

Similar to the calving module, the mortality record also has to go through an endorsement workflow. The workflow consists of five steps, which are Issued, Verified, Checked, Witnessed, and Approved. At any step, the record can be rejected with a reason. After the approval is complete, the admin can mark the case as completed or reopen it if needed. The code segment for the mortality records interface is shown in Figure 5.17, and the code segment for the mortality form is shown in Figure 5.18.

Figure 5.15: The mortality records list interface with pending approvals
Figure 5.16: The mortality record form interface
Figure 5.17: The code segment for mortality records interface
Figure 5.18: The code segment for mortality form interface

### 5.2.6 Health Treatment Module Interface

The health treatment module is used to record any treatment that is given to the cattle when they are sick. The main interface displays a table that lists all treatment records with their treatment number, cattle tag, category, date, symptoms, treatment code, and status as shown in Figure 5.19. The user can search for specific records using the search bar and filter by operating unit, month, and year.

To create a new treatment record, the user clicks on the "Record Treatment" button which opens the treatment form as shown in Figure 5.20. The form contains a cattle search field where the user can search and select the cattle by tag number. Once a cattle is selected, the system automatically fills in the category and colour. The user then enters the operating unit, date, symptoms, treatment code, dosage, and remarks. There is also an option to set a follow-up date if needed.

Each treatment record goes through a workflow of three steps, which are Prepared, Checked, and Approved. The workflow steps are assigned to different roles: Sr. Assistant Livestock prepares the record, Supervisor Livestock checks it, and Livestock Manager approves it. There is also a monthly workflow feature that allows the user to process multiple treatment records at once based on the month, year, and operating unit. The user can upload endorsement documents at each step and download them later. The code segment for the health treatment interface is shown in Figure 5.21, and the code segment for the treatment form is shown in Figure 5.22.

Figure 5.19: The health treatment list interface
Figure 5.20: The treatment record form interface
Figure 5.21: The code segment for health treatment interface
Figure 5.22: The code segment for treatment form interface

### 5.2.7 Transfer Module Interface

The transfer module is used to manage the movement of cattle between estates or locations. There are three types of transfer documents in the system, which are CTV (Cattle Transfer Voucher), Receival Document, and SIV (Sales Invoice Voucher). Each document type has its own interface and workflow. The CTV interface shows a list of all transfer vouchers as shown in Figure 5.23. The user can search for specific records and filter by date range.

To create a new CTV document, the user clicks on the "New CTV" button which opens the transfer form as shown in Figure 5.24. The form contains sections for document details, from and to locations, vehicle information, and a list of livestock being transferred. The user can add multiple cattle to the transfer list by searching and selecting them. Each transfer document goes through an eight-step workflow that includes Issued, Approved, Transported, Witness Transit, Verified Transit, Witness Receive, Received, and Completed. The workflow progress is displayed as a series of steps at the top of the document, and each step shows who signed off and when.

The Receival Document interface follows a similar pattern but is used for recording the receipt of cattle from another location. The SIV interface is used for recording the sale of cattle and includes additional fields for pricing and payment information. The code segment for the transfer module is shown in Figure 5.25.

Figure 5.23: The CTV transfer list interface
Figure 5.24: The CTV transfer form interface
Figure 5.25: The code segment for transfer module

### 5.2.8 Pasture and Grazing Module Interface

The pasture and grazing module is used to manage the land structure and the grazing activities in the estate. The main interface displays a list of all operating units with their herds and blocks as shown in Figure 5.26. The user can add a new operating unit by clicking on the "Add Operating Unit" button. Each operating unit can have multiple herds, and each herd can have multiple blocks. The blocks are further divided into phases.

The data input feature allows the user to record the grazing data for each estate as shown in Figure 5.27. The user can select an estate from the dropdown menu and enter the number of cattle, the rotation schedule, and the pasture condition. The herd card feature gives a detailed view of each estate and its grazing activities, including the total number of cattle, the area of each block, and the grazing phase. The code segment for the pasture module is shown in Figure 5.28.

Figure 5.26: The pasture management interface
Figure 5.27: The grazing data input interface
Figure 5.28: The code segment for pasture module

### 5.2.9 Feeding Module Interface

The feeding module is used to record the daily feeding activities of the cattle. The main interface displays a table that lists all feeding records with the herd, feeding option, date, and quantity as shown in Figure 5.29. The user can add a new feeding record by clicking on the "Add Record" button which opens a form modal. The form contains fields for selecting the herd, choosing the feeding option, entering the date, and specifying the quantity of feed given.

The feeding schedule interface shows a calendar view of all scheduled feeding activities as shown in Figure 5.30. The user can navigate through the calendar to view past and upcoming feeding schedules. The feeding records can be exported to a PDF file for documentation purposes. The code segment for the feeding module is shown in Figure 5.31.

Figure 5.29: The feeding records list interface
Figure 5.30: The feeding schedule interface
Figure 5.31: The code segment for feeding module

### 5.2.10 Inventory Module Interface

The inventory module is used to manage the supplies in the system. There are three sub-modules, which are feed management, medication management, and stock management. The medication management interface displays a table that lists all available medications with their names, types, stock levels, and status as shown in Figure 5.32. The user can add, edit, or delete medication records. The stock management interface shows the current stock levels and allows the user to perform bulk operations such as stock adjustments and transfers as shown in Figure 5.33. The supplier management feature allows the user to maintain a list of suppliers with their contact details and address. The code segment for the inventory module is shown in Figure 5.34.

Figure 5.32: The medication management interface
Figure 5.33: The stock management interface
Figure 5.34: The code segment for inventory module

### 5.2.11 Driver and Delivery Module Interface

The driver and delivery module is used to manage the driver profiles and the delivery records. The driver list interface shows all registered drivers with their names, contact information, and assigned vehicles as shown in Figure 5.35. The shift schedule interface displays a weekly calendar view of driver assignments as shown in Figure 5.36. The delivery history interface lists all past deliveries with their dates, routes, and status. The user can add, view, update, and delete delivery records. A bulk delete function is also available for the delivery history. The code segment for the driver module is shown in Figure 5.37.

Figure 5.35: The driver list interface
Figure 5.36: The shift schedule interface
Figure 5.37: The code segment for driver module

### 5.2.12 Access Control and User Management Module Interface

The access control and user management module is used by the admin to manage all the user accounts in the system. The user management interface displays a table that lists all registered users with their names, email addresses, roles, and status as shown in Figure 5.38. The admin can add a new user by clicking on the "Add User" button which opens a form where the name, email, password, role, and status can be entered. The admin can also edit or delete existing user accounts.

The access control matrix interface allows the admin to set the permission level for each user for every module as shown in Figure 5.39. The permission levels include no-access, view, create, edit, full, and approve. The admin can also configure the workflow assignments for the Treatment, Calving, and Transfer modules through this interface. There is also a field-level permission page that allows the admin to control which specific fields a user can see or edit. The audit logs interface tracks all important actions in the system with details of who did what and when as shown in Figure 5.40. The code segment for the user management is shown in Figure 5.41.

Figure 5.38: The user management interface
Figure 5.39: The access control matrix interface
Figure 5.40: The audit logs interface
Figure 5.41: The code segment for user management

## 5.3 Testing

After the system's completion, a testing phase is conducted to perform functional testing. The primary goal of functional testing is to ensure that each feature of the software functions according to the specified requirements. Testing is essential to uncover syntax errors, bugs, and security vulnerabilities, as well as to evaluate the system's usability, functionality and overall user experience. To verify that the system meets the derived requirements, a test case document is created. This document includes a set of test data, description and expected outcomes for each test scenario, ensuring comprehensive validation of the system.

### 5.3.1 Test Cases for Login Module (Test_100)

Table 5.1 outlines the test cases conducted during the functional testing phase for the login module. Each test case is designed to verify that the authentication process works correctly and that the system properly handles both valid and invalid login attempts.

Table 5.1: List of login test case

| No. | Test Cases | Description | Result |
|-----|------------|-------------|--------|
| 1. | TEST_100_001 | Launch the login page when accessing the system URL | PASS |
| 2. | TEST_100_002 | Launch the dashboard after inserting valid email and password | PASS |
| 3. | TEST_100_003 | Launch error message for the wrong password inserted | PASS |
| 4. | TEST_100_004 | Launch error message when email field is left empty | PASS |
| 5. | TEST_100_005 | Launch error message when password field is left empty | PASS |
| 6. | TEST_100_006 | Launch the correct dashboard based on the user role after login | PASS |

### 5.3.2 Test Cases for Cattle Management Module (Test_200)

Table 5.2 lists the test cases conducted to evaluate the functionality of the cattle management module. These test cases ensure that the process of adding, viewing, updating, deleting, searching, and exporting cattle records is executed successfully.

Table 5.2: List of cattle management test case

| No. | Test Cases | Description | Result |
|-----|------------|-------------|--------|
| 1. | TEST_200_001 | Launch the cattle list page when clicks on the Cattle Directory menu | PASS |
| 2. | TEST_200_002 | Launch the add cattle form when clicks on the Add Cattle button | PASS |
| 3. | TEST_200_003 | Launch the cattle record in the list after filling the form and saving | PASS |
| 4. | TEST_200_004 | Launch the updated cattle record after editing the details | PASS |
| 5. | TEST_200_005 | Launch confirmation message and remove the record after deletion | PASS |
| 6. | TEST_200_006 | Launch filtered results when searching cattle by tag number | PASS |
| 7. | TEST_200_007 | Launch the Excel file after clicking the export button | PASS |

### 5.3.3 Test Cases for Calving Records Module (Test_300)

Table 5.3 summarizes the test cases executed to verify the calving records functionalities of the system. These test cases confirm that the calving record creation, endorsement workflow, and document generation functions are working correctly.

Table 5.3: List of calving records test case

| No. | Test Cases | Description | Result |
|-----|------------|-------------|--------|
| 1. | TEST_300_001 | Launch the calving form when clicks on the Record Calving button | PASS |
| 2. | TEST_300_002 | Launch the calving record in the list after saving with valid details | PASS |
| 3. | TEST_300_003 | Launch the endorsement step as Issued after clicking the Issue button | PASS |
| 4. | TEST_300_004 | Launch the endorsement step as Verified after supervisor verifies | PASS |
| 5. | TEST_300_005 | Launch the endorsement step as Witnessed after security witnesses | PASS |
| 6. | TEST_300_006 | Launch the endorsement step as Approved after manager approves | PASS |
| 7. | TEST_300_007 | Launch the LCC document for download after the record is fully approved | PASS |

### 5.3.4 Test Cases for Mortality Records Module (Test_400)

Table 5.4 presents the test cases performed to validate the mortality records functionalities of the system. These test cases ensure that all of the mortality record creation, endorsement workflow, and post-mortem examination features are operating as intended.

Table 5.4: List of mortality records test case

| No. | Test Cases | Description | Result |
|-----|------------|-------------|--------|
| 1. | TEST_400_001 | Launch the mortality form when clicks on the Record Mortality button | PASS |
| 2. | TEST_400_002 | Launch the mortality record in the list after saving with valid details | PASS |
| 3. | TEST_400_003 | Launch the endorsement step as Issued after clicking the Issue button | PASS |
| 4. | TEST_400_004 | Launch the endorsement step as Verified after supervisor verifies | PASS |
| 5. | TEST_400_005 | Launch the endorsement step as Witnessed after security witnesses | PASS |
| 6. | TEST_400_006 | Launch the endorsement step as Approved after manager approves | PASS |
| 7. | TEST_400_007 | Launch the post-mortem examination form when clicks on the PM Exam button | PASS |

### 5.3.5 Test Cases for Health Treatment Module (Test_500)

Table 5.5 lists the test cases conducted to evaluate the functionality of the health treatment module. These test cases ensure that the process of creating treatment records, processing the endorsement workflow, and generating reports are executed successfully.

Table 5.5: List of health treatment test case

| No. | Test Cases | Description | Result |
|-----|------------|-------------|--------|
| 1. | TEST_500_001 | Launch the treatment form when clicks on the Record Treatment button | PASS |
| 2. | TEST_500_002 | Launch the treatment record in the list after saving with valid details | PASS |
| 3. | TEST_500_003 | Launch the endorsement step as Prepared after the assigned user prepares | PASS |
| 4. | TEST_500_004 | Launch the endorsement step as Checked after supervisor checks | PASS |
| 5. | TEST_500_005 | Launch the endorsement step as Approved after manager approves | PASS |
| 6. | TEST_500_006 | Launch the treatment report after clicking the export button | PASS |

### 5.3.6 Test Cases for Transfer Module (Test_600)

Table 5.6 details the test cases performed to assess the functionality of the transfer module. Each test case ensures that the CTV, Receival, and SIV document creation and the eight-step endorsement workflow functions well.

Table 5.6: List of transfer test case

| No. | Test Cases | Description | Result |
|-----|------------|-------------|--------|
| 1. | TEST_600_001 | Launch the CTV form when clicks on the New CTV button | PASS |
| 2. | TEST_600_002 | Launch the transfer record in the list after saving with valid details | PASS |
| 3. | TEST_600_003 | Launch the Receival form when clicks on the New Receival button | PASS |
| 4. | TEST_600_004 | Launch the SIV form when clicks on the New SIV button | PASS |
| 5. | TEST_600_005 | Launch the endorsement workflow progress after each step is completed | PASS |
| 6. | TEST_600_006 | Launch the transfer document as Completed after all eight steps are done | PASS |
| 7. | TEST_600_007 | Launch the PDF transfer document for download after the workflow is complete | PASS |

### 5.3.7 Test Cases for Inventory Module (Test_700)

Table 5.7 lists the test cases conducted to evaluate the functionality of the inventory module. These test cases ensure that the medication management, stock tracking, and supplier management features are executed successfully.

Table 5.7: List of inventory test case

| No. | Test Cases | Description | Result |
|-----|------------|-------------|--------|
| 1. | TEST_700_001 | Launch the medication list when clicks on the Medication menu | PASS |
| 2. | TEST_700_002 | Launch the medication record in the list after adding with valid details | PASS |
| 3. | TEST_700_003 | Launch the updated medication record after editing the details | PASS |
| 4. | TEST_700_004 | Launch confirmation message and remove the medication after deletion | PASS |
| 5. | TEST_700_005 | Launch the stock list with correct quantities after adding stock | PASS |

### 5.3.8 Test Cases for Pasture, Feeding, and Driver Module (Test_800)

Table 5.8 details the test cases performed to assess the functionality of the pasture, feeding, and driver modules. Each test case ensures that the grazing data input, feeding records, and delivery history functions well.

Table 5.8: List of pasture, feeding, and driver test case

| No. | Test Cases | Description | Result |
|-----|------------|-------------|--------|
| 1. | TEST_800_001 | Launch the pasture management page when clicks on the Pasture menu | PASS |
| 2. | TEST_800_002 | Launch the grazing data after saving with valid estate details | PASS |
| 3. | TEST_800_003 | Launch the feeding record in the list after adding with valid details | PASS |
| 4. | TEST_800_004 | Launch the feeding schedule in calendar view | PASS |
| 5. | TEST_800_005 | Launch the delivery history list with correct records | PASS |

### 5.3.9 Test Cases for Access Control Module (Test_900)

Table 5.9 shows the test cases that were carried out for the access control and user management module. These test cases were used to verify that the user management, permission settings, and audit logging functions are all working correctly.

Table 5.9: List of access control test case

| No. | Test Cases | Description | Result |
|-----|------------|-------------|--------|
| 1. | TEST_900_001 | Launch the user management page when clicks on the Users menu | PASS |
| 2. | TEST_900_002 | Launch the new user in the list after adding with valid details | PASS |
| 3. | TEST_900_003 | Launch the updated user record after editing the role and status | PASS |
| 4. | TEST_900_004 | Launch the access control matrix when clicks on the Access Control menu | PASS |
| 5. | TEST_900_005 | Launch the permission changes saved after updating the matrix | PASS |
| 6. | TEST_900_006 | Launch the audit log entry after a user action is performed | PASS |

### 5.3.10 Overall Result

During the testing phase, 55 test cases were executed to evaluate, verify and validate the system's functionalities. The system's modules performed as expected and the overall outcome is successful. The comprehensive results are presented in Table 5.10.

Table 5.10: List of overall results test cases

| Test Case | Total Test Case | Total Passes | Total Fails |
|-----------|-----------------|--------------|-------------|
| TEST_100 | 6 | 6 | - |
| TEST_200 | 7 | 7 | - |
| TEST_300 | 7 | 7 | - |
| TEST_400 | 7 | 7 | - |
| TEST_500 | 6 | 6 | - |
| TEST_600 | 7 | 7 | - |
| TEST_700 | 5 | 5 | - |
| TEST_800 | 5 | 5 | - |
| TEST_900 | 6 | 6 | - |
| **Total** | **55** | **55** | **-** |

## 5.4 Conclusion

This chapter describes the thorough implementation and functional testing that was done on the system to make sure all of the features worked as intended. Nine main areas such as login, cattle management, calving records, mortality records, health treatment, transfer documents, inventory, pasture and feeding, and access control were the focus of the testing. Every test case is made to confirm features, like page loads, data addition, changes, removals, and user access rights. Every test passed, attesting to the system's dependability, security, and ease of use. The endorsement workflow system that was implemented across the calving, mortality, health treatment, and transfer modules was tested and confirmed to be working as intended with each step properly tracking the approver's name, date, and signature. This extensive validation procedure guarantees that the system satisfies its specifications, offering a reliable and effective solution that is prepared for implementation at Sawit Kinabalu Farm Products Sdn Bhd.

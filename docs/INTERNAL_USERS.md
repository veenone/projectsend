# Internal Users Feature

## Overview

The **Internal User** role is designed for internal company employees who need file access but should not have administrative privileges. This role is particularly useful for organizations deploying ProjectSend internally where regular employees need to:

- Upload and share files
- Access files shared with them
- Collaborate via groups and folders
- Authenticate using LDAP/Active Directory

This role provides a middle ground between **Client** users (external recipients with minimal access) and **System Users** (internal administrators with full access).

## Key Features

### Permissions

Internal Users have the following default permissions:

✅ **File Management**
- Upload new files
- Edit their own uploaded files
- Delete their own files
- Assign categories to files
- Mark files as public (for company-wide sharing)

✅ **Account Management**
- Edit their own profile information
- Change password (unless LDAP-authenticated)

❌ **Restricted Access**
- Cannot access admin panel
- Cannot manage other users or clients
- Cannot modify system settings
- Cannot view action logs or statistics
- Cannot manage groups (only participate in them)

### Integration with Existing Features

**LDAP Authentication:**
- Seamlessly works with LDAP/Active Directory
- Auto-creates Internal User accounts on first login
- Configurable via Admin → Options → LDAP → Default role

**File Sharing:**
- Can share files via **Groups** - create department/team groups
- Can use **Public Folders** - shared company directories
- Can mark files as **Public** - accessible via URL
- **Note:** Internal Users cannot be directly assigned files like Clients (by design)

**Collaboration Workflows:**
- **Department Sharing**: Create groups (HR, IT, Finance) and add internal users
- **Document Library**: Use public folders for company-wide resources
- **Project Collaboration**: Combine groups + folders for team projects

## Setup Instructions

### Automatic Setup (Database Upgrade)

The Internal User role is automatically created when you upgrade to database version 2025112501 or later. The upgrade will:

1. Create the "Internal User" role
2. Set appropriate default permissions
3. Configure LDAP to use this role as default (if LDAP is enabled)

No manual intervention is required!

### Manual Configuration (If Needed)

If you need to manually create or modify the Internal User role:

#### 1. Create Role via UI

1. Login as **System Administrator**
2. Navigate to **Users & Roles → Roles**
3. Click **"Add New Role"**
4. Configure:
   ```
   Name: Internal User
   Description: Internal employees with file access
   Active: Yes
   Permissions Editable: Yes
   ```
5. Select permissions:
   - ✅ upload
   - ✅ edit_files
   - ✅ delete_files
   - ✅ edit_self_account
   - ✅ set_file_categories
   - ✅ upload_public

#### 2. Configure LDAP Default Role

1. Navigate to **Options → LDAP**
2. Under **"New Accounts"** section
3. Find **"Default role for new LDAP users"**
4. Select **"Internal User"** from dropdown
5. Save settings

#### 3. Configure via SQL (Direct Database)

```sql
-- Get the Internal User role ID
SELECT id, name FROM tbl_roles WHERE name = 'Internal User';

-- Set as LDAP default (replace <ROLE_ID> with actual ID)
UPDATE tbl_options
SET value = '<ROLE_ID>'
WHERE name = 'ldap_default_role';
```

## File Sharing Workflows

Since Internal Users cannot be directly assigned files like Clients, use these recommended workflows:

### Option 1: Department Groups (Recommended)

**Use Case:** Share files within specific departments or teams

**Setup:**
1. Create groups for each department (HR, IT, Finance, etc.)
2. Add internal users to their respective groups
3. When uploading files, assign to relevant groups
4. Users automatically see files assigned to their groups

**Example:**
```
Group: "IT Department"
Members: john@company.com, jane@company.com, bob@company.com

Upload file → Assign to "IT Department" group → All members can access
```

### Option 2: Public Folders

**Use Case:** Company-wide document libraries

**Setup:**
1. Create public folders (Policies, Forms, Resources)
2. Grant Internal Users permission to upload to public folders
3. Users can browse and upload to shared folders
4. No explicit assignment needed

**Example:**
```
Folder Structure:
├── Company Policies (Public)
├── HR Forms (Public)
├── IT Resources (Public)
└── Training Materials (Public)
```

### Option 3: Public Files

**Use Case:** Quick sharing via direct links

**Workflow:**
1. Internal User uploads file
2. Marks file as "Public"
3. Shares public URL with colleagues
4. Anyone with link can access

### Option 4: Mixed Approach (Best for Most Organizations)

Combine all three methods:
- **Public Folders** for company-wide documents
- **Groups** for department-specific files
- **Public Links** for ad-hoc sharing

## Migration Guide

### Converting Existing Clients to Internal Users

If you have existing client users who should be Internal Users:

```sql
-- Get the Internal User role ID
SET @internal_role_id = (SELECT id FROM tbl_roles WHERE name = 'Internal User');

-- Convert clients from your company domain
UPDATE tbl_users
SET role_id = @internal_role_id
WHERE email LIKE '%@yourcompany.com'
AND role_id = (SELECT id FROM tbl_roles WHERE name = 'Client');
```

### Converting System Users to Internal Users

For system users who don't need admin access:

```sql
-- Get role IDs
SET @internal_role_id = (SELECT id FROM tbl_roles WHERE name = 'Internal User');
SET @uploader_role_id = (SELECT id FROM tbl_roles WHERE name = 'Uploader');

-- Convert specific uploaders to internal users
UPDATE tbl_users
SET role_id = @internal_role_id
WHERE role_id = @uploader_role_id
AND email IN ('user1@company.com', 'user2@company.com');
```

## LDAP Integration

### Automatic Account Creation

When LDAP is configured with Internal User as the default role:

1. User authenticates via LDAP (Active Directory, OpenLDAP, etc.)
2. System checks if local account exists
3. If not, creates new account with Internal User role
4. User is logged in and can immediately start using the system

### Configuration Options

**LDAP Settings** (Options → LDAP):
- **Default role**: Select "Internal User"
- **Auto-create users**: Enable to automatically create accounts
- **Disable password change**: Enable to force LDAP password management

### Testing LDAP Setup

1. Configure LDAP settings
2. Set default role to "Internal User"
3. Use "Test LDAP Connection" button
4. Try logging in with LDAP credentials
5. Verify new account has Internal User role

## Permissions Reference

### What Internal Users CAN Do

| Permission | Description |
|------------|-------------|
| Upload files | Upload new files to the system |
| Edit own files | Modify metadata, description, categories |
| Delete own files | Remove files they uploaded |
| Set categories | Organize files with categories |
| Mark as public | Make files accessible via public URL |
| Edit profile | Update own account information |
| Join groups | Be added to groups by administrators |
| Access group files | View files shared with their groups |
| Browse public folders | Access shared company folders |

### What Internal Users CANNOT Do

| Restriction | Why |
|-------------|-----|
| Manage other users | Admin/Manager privilege only |
| Access system settings | Admin privilege only |
| View action logs | Admin privilege only |
| Create/manage groups | Admin/Manager privilege only |
| Edit others' files | Protect user ownership |
| Delete others' files | Protect user ownership |
| Be assigned files directly | Use groups/folders instead |

## Troubleshooting

### Issue: "Internal users can't see files"

**Solution**: Files must be shared via groups or public folders, not direct assignment.

**Steps:**
1. Create a group for the users
2. Add internal users to the group
3. Assign files to the group
4. OR create public folders and upload files there

### Issue: "LDAP users getting wrong role"

**Solution**: Check LDAP default role setting.

**Steps:**
1. Go to Options → LDAP
2. Verify "Default role for new LDAP users" is set to "Internal User"
3. Save settings
4. Test with new LDAP login

### Issue: "Internal users see admin menu"

**Solution**: User likely has wrong role assigned.

**Steps:**
```sql
-- Check user's current role
SELECT u.id, u.email, u.name, r.name as role_name
FROM tbl_users u
JOIN tbl_roles r ON u.role_id = r.id
WHERE u.email = 'user@company.com';

-- Fix if needed (replace USER_ID and INTERNAL_ROLE_ID)
UPDATE tbl_users
SET role_id = <INTERNAL_ROLE_ID>
WHERE id = <USER_ID>;
```

### Issue: "Need to assign files to specific internal user"

**Solution**: Create a single-member group.

**Workaround:**
1. Create group named after the user (e.g., "John Doe - Personal")
2. Add only that user to the group
3. Assign files to that group
4. Acts like direct assignment

## API Reference

### Helper Methods

```php
// Get Internal User role ID
$internal_role_id = \ProjectSend\Classes\Roles::getInternalUserRoleId();

// Check if user is Internal User
$user = new \ProjectSend\Classes\Users($user_id);
$role_data = $user->getRoleData();
$is_internal = ($role_data['name'] === 'Internal User');

// Get all users with Internal User role
global $dbh;
$sql = "SELECT u.* FROM tbl_users u
        JOIN tbl_roles r ON u.role_id = r.id
        WHERE r.name = 'Internal User'
        AND u.active = 1";
$statement = $dbh->prepare($sql);
$statement->execute();
$internal_users = $statement->fetchAll(PDO::FETCH_ASSOC);
```

## Best Practices

### 1. Plan Your Group Structure

Before adding users, design your group hierarchy:
- Department-based (HR, IT, Finance)
- Project-based (Project Alpha, Project Beta)
- Location-based (HQ, Branch Office)
- Mixed approach

### 2. Use Clear Naming Conventions

```
Good:
- IT_Department
- HR_Team
- Project_CloudMigration

Bad:
- Group1
- Test
- Misc
```

### 3. Public Folders for Common Resources

Reserve public folders for:
- Company policies
- Forms and templates
- Training materials
- General announcements

### 4. Groups for Restricted Content

Use groups for:
- Department-specific files
- Project files
- Confidential documents
- Team collaboration spaces

### 5. Monitor and Clean Up

Regularly:
- Review group memberships
- Remove inactive users
- Archive old projects
- Clean up orphaned files

## Security Considerations

### Principle of Least Privilege

Internal Users have minimal permissions by design:
- Can't escalate privileges
- Can't access admin functions
- Can't manage other users
- Can't view system logs

### LDAP Password Management

When using LDAP:
- Enable "Disable local password change"
- Force password management through Active Directory
- Leverage your existing password policies
- Enable 2FA at LDAP level if possible

### File Visibility

Files are only visible to Internal Users if:
- Assigned to a group they're in
- In a public folder
- Marked as public (via URL)

Internal Users **cannot** browse all files in the system.

## Future Enhancements

Potential features for Internal User role:
- Sub-roles (Internal Viewer, Internal Contributor)
- Custom permission sets per organization
- Department-level administrators
- Self-service group management
- File approval workflows

## Support

For issues or questions about the Internal User role:
1. Check this documentation
2. Review LDAP configuration
3. Verify role permissions
4. Check system logs
5. Contact your system administrator

## Related Documentation

- [LDAP Configuration Guide](LDAP.md)
- [Roles and Permissions](ROLES.md)
- [Groups Management](GROUPS.md)
- [Folders Structure](FOLDERS.md)

---

**Version:** 1.0
**Last Updated:** November 25, 2025
**Compatible With:** ProjectSend r1945 and later

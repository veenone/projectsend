# ProjectSend Single Sign-On (SSO) User Guide

## Overview

ProjectSend now supports Single Sign-On (SSO) authentication, allowing you to log in using your corporate credentials without needing a separate password. This guide will help you understand how to use SSO with ProjectSend.

## What is Single Sign-On (SSO)?

Single Sign-On allows you to access ProjectSend using the same username and password you use for other company applications. Once you log in through your organization's identity provider (like Microsoft Azure, Google Workspace, or your company's internal system), you'll automatically have access to ProjectSend without needing to remember another password.

### Benefits of SSO

- **Single Password**: Use your corporate credentials for all applications
- **Enhanced Security**: Your organization manages password policies and security
- **Easy Access**: Quick login without remembering multiple passwords
- **Automatic Updates**: Your access is updated when your company role changes

## Getting Started with SSO

### First-Time Setup

When SSO is enabled, you'll see a new login option on the ProjectSend login page.

#### Step 1: Navigate to ProjectSend Login

1. Open your web browser
2. Go to your organization's ProjectSend URL
3. You'll see the login page with SSO options

#### Step 2: Choose SSO Login

Instead of entering a username and password, click the SSO login button. This might be labeled as:
- "Login with Corporate Account"
- "Sign in with Microsoft"
- "Login with Google"
- "Company SSO Login"

The exact text depends on how your administrator configured the system.

#### Step 3: Authenticate with Your Organization

1. You'll be redirected to your organization's login page
2. Enter your corporate username and password
3. Complete any additional security steps (like two-factor authentication) required by your organization
4. You'll be redirected back to ProjectSend automatically

#### Step 4: Complete Your Profile (First Login Only)

On your first login, ProjectSend may ask you to:
- Confirm your contact information
- Set notification preferences
- Review your assigned role and permissions

## Daily Usage

### Logging In

1. Go to the ProjectSend login page
2. Click the SSO login button
3. If you're already logged into your corporate systems, you may be logged in automatically
4. If not, enter your corporate credentials when prompted

### Logging Out

To fully log out:
1. Click "Logout" in ProjectSend
2. This will log you out of both ProjectSend and your corporate authentication system
3. Close your browser for complete security

## Understanding Your Access

### User Roles

Your access level in ProjectSend is determined by your corporate group membership:

#### Administrator
- Full system access
- Can manage users and settings
- Can upload and share files
- Can create and manage clients
- Typically assigned to IT staff or department managers

#### User
- Can upload files and create folders
- Can share files with clients
- Can manage their own content
- Can create client accounts
- Typically assigned to regular employees

#### Client
- Can only download files shared with them
- Cannot upload files
- Limited system access
- Typically assigned to external users or customers

### Group Membership

Your role is automatically assigned based on your corporate group membership:
- If you're in the "ProjectSend Administrators" group, you'll have administrator access
- If you're in the "ProjectSend Users" group, you'll have user access
- Other groups may be mapped to specific roles by your administrator

## Common Tasks

### Uploading Files

1. Log in using SSO
2. Click "Upload Files" or go to the Files section
3. Select files from your computer
4. Choose recipients (if you're a User or Administrator)
5. Add descriptions and set expiration dates if needed
6. Click "Upload"

### Sharing Files

1. Navigate to your uploaded files
2. Select the files you want to share
3. Click "Share" or "Send"
4. Choose recipients from the client list
5. Add a personal message
6. Click "Send"

### Managing Your Profile

1. Click your name in the top-right corner
2. Select "My Account" or "Profile"
3. Update your information as needed
4. Note: Some information (like email and name) may be automatically updated from your corporate directory

## Troubleshooting

### Cannot Access SSO Login

**Problem**: Don't see SSO login option

**Solutions**:
- Contact your IT administrator - SSO may not be enabled yet
- Try refreshing the page
- Clear your browser cache and cookies
- Try a different browser

### Login Fails

**Problem**: Error message during login

**Common Causes**:
- **"Access Denied"**: Your corporate account may not have ProjectSend access
- **"Invalid Credentials"**: Check your corporate username and password
- **"Session Expired"**: Try logging in again
- **"Account Locked"**: Contact your IT administrator

**Solutions**:
- Verify you're using your correct corporate credentials
- Check if your corporate account is active
- Contact your IT administrator if the problem persists

### Unexpected Logout

**Problem**: Logged out automatically

**Causes**:
- Your corporate session expired
- Company security policies enforced logout
- Browser closed or computer went to sleep

**Solutions**:
- Log in again using SSO
- Contact IT if this happens frequently
- Check your corporate password hasn't expired

### Wrong Access Level

**Problem**: Don't have expected permissions

**Solutions**:
- Check with your manager about your expected access level
- Contact IT to verify your group membership
- Your access may need to be updated in the corporate directory

### Files Not Visible

**Problem**: Cannot see expected files

**Reasons**:
- Files may be shared with a different account
- Your role may have changed
- Files may have been moved or deleted

**Solutions**:
- Check with the person who shared files with you
- Contact your administrator to verify your account
- Search for files using the search function

## Mobile Access

### Using SSO on Mobile Devices

1. Open your mobile browser
2. Navigate to ProjectSend
3. Tap the SSO login button
4. Complete authentication on your organization's mobile-friendly login page
5. You may be asked to install or use your organization's mobile app for authentication

### Mobile App (if available)

If your organization provides a ProjectSend mobile app:
1. Download from your organization's app store or internal distribution
2. Use the same SSO credentials
3. Follow the in-app setup instructions

## Security Best Practices

### Protecting Your Account

1. **Use Strong Corporate Password**: Follow your organization's password policy
2. **Enable Two-Factor Authentication**: If available through your organization
3. **Log Out When Done**: Especially on shared computers
4. **Keep Browser Updated**: Use the latest browser version
5. **Report Issues**: Contact IT immediately if you notice unusual activity

### Safe File Sharing

1. **Verify Recipients**: Make sure you're sharing with the right people
2. **Use Expiration Dates**: Set files to expire when no longer needed
3. **Check Permissions**: Understand who can see your shared files
4. **Avoid Sensitive Data**: Don't upload highly confidential information without approval

## Getting Help

### Self-Service Options

1. **Help Documentation**: Look for "Help" or "Documentation" links in ProjectSend
2. **FAQ Section**: Check if your organization has a ProjectSend FAQ
3. **User Manual**: This guide and other documentation may be available in the system

### Contacting Support

#### For Login Issues:
- Contact your IT help desk
- Use your organization's standard support channels
- Provide specific error messages when reporting issues

#### For ProjectSend Features:
- Contact your ProjectSend administrator
- Check if your organization has ProjectSend-specific support
- Ask colleagues who also use ProjectSend

### Information to Provide When Getting Help

When contacting support, include:
- Your corporate username (don't include password)
- Exact error messages
- What you were trying to do
- Your browser and version
- Whether the problem happens consistently

## Frequently Asked Questions

### Q: Do I need a separate ProjectSend password?
**A:** No, you use your corporate credentials to log in via SSO.

### Q: What if I forget my corporate password?
**A:** Use your organization's standard password reset process, not ProjectSend's.

### Q: Can I still access ProjectSend if I'm not connected to the company network?
**A:** Yes, as long as you can access your corporate authentication system from your location.

### Q: What happens to my files if I leave the company?
**A:** Your access will be automatically removed when your corporate account is deactivated. Files you uploaded may remain in the system depending on company policy.

### Q: Can I use SSO and a regular ProjectSend password?
**A:** This depends on your organization's configuration. Some allow both, others require SSO only.

### Q: Why do I sometimes get logged in automatically?
**A:** If you're already logged into your corporate systems, SSO may automatically log you into ProjectSend without requiring credentials again.

### Q: Can I access ProjectSend from multiple devices?
**A:** Yes, you can log in from any device using your corporate credentials.

### Q: What browsers are supported for SSO?
**A:** Most modern browsers support SSO. Check with your IT department for specific requirements.

### Q: Is my data secure with SSO?
**A:** Yes, SSO often provides better security than separate passwords because it's managed by your organization's security team.

### Q: Can I share files with people outside my organization?
**A:** This depends on your role and organization policy. Contact your administrator for guidance.

## Updates and Changes

### When SSO Settings Change

Your organization may occasionally update SSO settings. When this happens:
- You may need to log in again
- The login process might look slightly different
- You should receive communication from IT about any major changes

### Keeping This Guide Current

This guide may be updated when new features are added. Check for updated versions periodically or when you notice changes in the system.

---

**Need immediate help?** Contact your IT help desk or system administrator. For general questions about ProjectSend features, refer to the main ProjectSend documentation or contact your designated ProjectSend administrator.
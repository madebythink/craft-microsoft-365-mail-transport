# Microsoft365 Mail Transport for Craft CMS

This plugin allows Craft CMS to send email using the Microsoft Graph API, authenticating with OAuth 2.0.

## Requirements

- Craft CMS 5.0+
- PHP 8.0+
- An Azure Active Directory account with permissions to create and manage App Registrations.
- A licensed Microsoft 365 mailbox to send email from.

## Installation

1.  Install with Composer:
    ```bash
    composer require madebythink/craft-microsoft-365-mail-transport
    ```
2.  In the Craft Control Panel, go to Settings > Plugins and install the "Microsoft 365 Mail Transport" plugin.

## Azure App Registration Setup

You must create an App Registration in Azure AD to get the credentials this plugin needs.

1.  **Log in** to the [Azure Portal](https://portal.azure.com/).
2.  Navigate to **Azure Active Directory**.
3.  Go to **App registrations** and click **+ New registration**.
4.  Give your application a **Name** (e.g., "Craft CMS Mailer").
5.  For **Supported account types**, select **"Accounts in this organizational directory only (Single tenant)"**.
6.  Click **Register**.

### Step 1: Get Tenant ID and Client ID

-   On the **Overview** page for your new app registration, copy the **Directory (tenant) ID** and the **Application (client) ID**. You will need these for the plugin settings in Craft.

### Step 2: Create a Client Secret

1.  In the app registration menu, go to **Certificates & secrets**.
2.  Click **+ New client secret**.
3.  Give it a description (e.g., "Craft Plugin Secret") and choose an expiry duration.
4.  Click **Add**.
5.  **IMMEDIATELY COPY THE "VALUE"**. This is your Client Secret. It will be hidden after you leave this page.

### Step 3: Grant API Permissions

1.  In the app registration menu, go to **API permissions**.
2.  Click **+ Add a permission**.
3.  Select **Microsoft Graph**.
4.  Select **Application permissions**. (Important: NOT "Delegated permissions").
5.  In the "Select permissions" search box, type `Mail.Send` and check the box next to **Mail.Send**.
6.  Click **Add permissions**.

### Step 4: Grant Admin Consent

-   Because you added an *Application* permission, an administrator must grant consent.
-   On the **API permissions** page, click the **"Grant admin consent for [Your Directory Name]"** button. The status for the `Mail.Send` permission should change to "Granted".

## Plugin Configuration

1.  In the Craft Control Panel, go to **Settings > Email**.
2.  For the **Transport Type**, select **"Microsoft 365 (Graph API)"**.
3.  Fill in the settings fields with the values you copied from Azure:
    *   **Tenant ID**: Your Directory (tenant) ID.
    *   **Client ID**: Your Application (client) ID.
    *   **Client Secret**: The secret *value* you created.
    *   **From Email Address**: The email address of the licensed mailbox you want to send emails from (e.g., `noreply@yourdomain.com`).
4.  Save the settings and use the "Test" utility to send a test email.

It's highly recommended to store your credentials as environment variables in your `.env` file and reference them in the settings fields (e.g., `$M365_CLIENT_ID`).
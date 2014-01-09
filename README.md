OAuth authentication via Google
===============================
Here are the steps to perform in order to configure the service.
 * Log in on your google account and go to https://cloud.google.com/console.
   Create a project, then under its APIs & auth/Credentials menu, create a new client ID.
   * keep 'Web application' for Application type
   * put your callback URL(s) in *Authorized redirect URI*. *Attention*: the callback URIs must be public(not local like 'domain.lan'), otherwise Google will refuse the redirection.

   Once the project created, some parameters will be generated. We need *Client ID* and *Client secret*.

 * Go to pfSense administration interface, activate the *OAuth2(via Google)* option(in *Authentication* section) for the zones you need to. Set the ID and the Secret and save the configuration.
 * In order to let Google's authentication page display properly, the IP for accounts.google.com and ssl.gstatic.com(probably depending on your country) should be opened on captive portal.

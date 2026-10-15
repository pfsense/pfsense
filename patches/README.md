# patches

Local patches carried in this repository for pfSense systems, applied with the
[System Patches](https://docs.netgate.com/pfsense/en/latest/packages/system-patches.html)
package (path strip = 1, base directory = `/`).

## pfsense-2.8.1-replyto-interface-groups.patch

Target: pfSense CE 2.8.1-RELEASE (`etc/inc/filter.inc`).

Rules bound to an **interface group** (e.g. `WAN_Group` = `wan` + `opt9`)
never received a `reply-to` directive, because `interface_has_gateway()` and
`interface_has_gatewayv6()` return false when the group's members are all
dynamic. With the system default route pointing at a VPN (0/1 + 128/1
routes), replies to inbound connections arriving through the group followed
the VPN instead of going back out the WAN, breaking port forwards and
certbot HTTP-01 renewals.

The patch extends the reply-to generation in `filter_generate_user_rule()`:

- builds `reply-to` from the first *online* member of the group, using the
  member's real interface (`igb0`, `ue0`, ...) instead of the group
  pseudo-interface, for both `inet` and `inet6` rules;
- resolves the member's gateway via `get_gateways()` and only uses gateways
  whose monitor status is `online` (so a down member is skipped, e.g. the
  mobile interface when no WAN failover is active);
- leaves existing behaviour for non-group interfaces unchanged (the helper
  returns `[null, null]`, so no reply-to is emitted unless the direct
  resolution already succeeded).

The upstream forward port for `master` (2.9.0-DEVELOPMENT) lives on the
`fix/replyto-interface-group` branch (`src/etc/inc/filter.inc`).

To apply on a 2.8.1 box: System > Patches > Add New Patch, paste the patch
content, set path strip to 1, then Apply. Revert it in System Patches before
removing the patch entry.

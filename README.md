pfSense on bootstrap
====================

We are migrating pfSense to Bootstrap. You can help! Please respect these code-guidelines:

* use tabs (tabstop=4) for indenting (except the license-header which contains 3 lines that are indented with "\t<SP><SP><SP>")
* no trailing whitespace
* limited echoing of HTML from php, please use proper templating syntax instead (eg. foreach/endforeach)
* limited attributes on elements; _no style attributes_
* no inline javascript
* html attributes should be using double-quoted attribute-values. This means your php-code should probably use single-quoted strings
* we use icons for status-indication and buttons for actions

If you feel adventurous you can sometimes rewrite some PHP & javascript code as well; but try to keep this to a minimum.

# Development setup

We suggest you setup a development enviroment for testing your changes. This can be done with either Virtualbox or Qemu. 

## Qemu

Use libvirt to setup a FreeBSD-10 machine with 2 NICs. Boot the latest pfSense.iso and install it. I've attached both NICs to the virbr0 that libvirt offers by default. One interface can be used as WAN (where pfSense will use dhcp and should get a NATted ip on your local network), the other as a LAN interface with a fixed IP address.

## Virtualbox

..

## Post install tasks

Disable the dhcp server (on the LAN interface) of your pfSense install and you're good to go. Start the ssh-daemon, login and setup public-key authentication (for the root user). Execute `pkg install rsync` and create a script to upload your changes from your development environment to your pfSense install:

```bash
#!/bin/sh

rsync -xav --delete `dirname $0`/usr/local/www/ root@192.168.122.100:/usr/local/www/
rsync -xav --delete `dirname $0`/etc/inc/ root@192.168.122.100:/etc/inc/
```

# Cleaner

Before diving into a template, clean it with the supplied cleaner (`clean.sh`). This script tries to remove most of the unnecessary element attributes and does a bunch of other replaces which have to be done in every template.

# Template migration conventions

All migrated templates are formatted with default [Bootstrap](http://getbootstrap.com/) components. Custom CSS goes into `usr/www/bootstrap/css/pfSense.css`, but try to keep this to a minimum.

The Bootstrap grid system is used for defining columns. We've chosen the 'small' breakpoint as the default breakpoint to collapse from a horizontal to vertical layout. You should define your column widths with `.col-sm-*`, unless there's a good (and documented ;) ) reason to deviate from this default.

## Forms

* Every form should have at least one 'panel' which contains the form fields. If certain fields can be grouped together, you can add multiple panels to a form.
* A field consists of an outer wrapper `.form-group` which contains a `label` and the `input`
* The submit button should be placed outside of the panels to prevent confusion (e.g., the save button saves the whole form and not just the last panel).
* Checkboxes are placed within a label (see example below). The wrapping div needs an additional `.checkbox` class
* Additional field descriptions can be placed in the `.help-block` `span`

An example (which omits everything but relevant Bootstrap classes):

```html
<form class="form-horizontal">
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title">Form or panel heading</h2>
		</div>
		<div class="panel-body">
			<div class="form-group">
				<label for="input" class="col-sm-2 control-label">Labeltext</label>
				<div class="col-sm-10">
					<input class="form-control" id="input" />
				</div>
			</div>
			<div class="form-group">
				<label for="second-input" class="col-sm-2 control-label">Second label</label>
				<div class="col-sm-10">
					<input class="form-control" id="second-input" />
					<span class="help-block">What's this all about?</span>
				</div>
			</div>
			<div class="form-group">
				<label for="second-input" class="col-sm-2 control-label">Checkbox</label>
				<div class="col-sm-10 checkbox">
					<label>
						<input type="checkbox" id="checkbox" /> Checkbox description
					</label>
				</div>
			</div>

			<!-- And more form-groups -->

		</div>
	</div>

	<!-- And more panels -->

	<div class="col-sm-10 col-sm-offset-2">
		<input type="submit" class="btn btn-primary" value="Save" />
	</div>
</form>
```

## Tables

* Wrap your tables in a `<div class="table-responsive">` to make them scroll horizontally on small devices.
* Tables get a standard set of classes: `table table-striped table-hover`
* Please add a `thead` (with corresponding `th`'s) and `tbody`

## Buttons

Many tables have 'action' buttons per row, like 'edit', 'move' and 'delete'. The old template uses icons for these actions, but in most cases there are not sufficient different icons and / or the icons aren't very self explanatory. We've chosen to replace these icons with (small) buttons:

```html
<a class="btn btn-xs btn-primary">edit</a> <a class="btn btn-xs btn-danger">delete</a>
```

The button colours aren't set in stone, but the variants used so far are:

* edit - dark blue, `btn-primary`
* enable / disable - yellow, `btn-warning`
* delete - red, `btn-danger`
* copy - neutral, `btn-default`

## Icons

Icons are primarily used for status indications. Try to supply a legend when the icon is not 100% self explanatory. See `usr/local/www/firewall_rules.php` for an good example of icon usage and a legend.
pfSense on bootstrap (<a href="https://github.com/SjonHortensius/pfsense/blob/bootstrap/PROGRESS.md#php-file-status">progress</a>)
====================

We are migrating pfSense to Bootstrap. You can help! Please respect these code-guidelines:

* use tabs (tabstop=4) for indenting (except the license-header which contains 3 lines that are indented with ```\t<SP><SP><SP>```)
* no trailing whitespace
* limited echoing of HTML from php, please use proper templating syntax instead (eg. foreach/endforeach)
* limited attributes on elements; so _**no** style/align/width attributes_
* no inline javascript, no ```&nbsp;```, no tables for layout (replace them with [panels](getbootstrap.com/components/#panels) where necessary)
* html attributes should be using double-quoted attribute-values. This means your php-code should probably use single-quoted strings
* we use icons for status-indication and buttons for actions
* **do not** refactor any of the 'backend' code that is on top of each file. Only changes necessary after updating are acceptable; any other changes will be rejected (including changes that were done upstream)
* we accept both [K&R](https://en.wikipedia.org/wiki/Indent_style#K.26R_style) and [ZF](http://framework.zend.com/manual/1.12/en/coding-standard.html) styled code, the above guidelines have a higher precedence

If you feel adventurous you can sometimes rewrite some PHP & javascript code as well; but try to keep this to a minimum.

# Development setup

We suggest you setup a development environment for testing your changes. This can be done with either Virtualbox or Qemu.

## Qemu

Use libvirt to setup a FreeBSD-10 machine with 2 NICs. Boot the latest pfSense.iso and install it. I've attached both NICs to the virbr0 that libvirt offers by default. One interface can be used as WAN (where pfSense will use dhcp and should get a NATted ip on your local network), the other as a LAN interface with a fixed IP address.

## Virtualbox

Create a new virtual machine (FreeBSD 64 bit) and follow the wizard to configure the amount of RAM (512MB) and create a virtual HDD (8GB will do). When finished, don't start the machine but open the settings dialog and configure two network adapters. Both should be configured as 'Bridged Adapter', attached to your active network connection.

Once saved, you can start the machine. Virtualbox will ask you to configure a bootable medium. Use the latest available .iso, follow the standard installation steps and set up the configuration as described in the Qemu instructions.

When finished, don't forget to remove the installation disk from your machine. Otherwise, it'll keep booting the installer instead of your installation.

## Post install tasks

Disable the dhcp server (on the LAN interface) of your pfSense install and you're good to go. Start the ssh-daemon, login and setup public-key authentication (for the root user). Execute `pkg install rsync` and create a script to upload your changes from your development environment to your pfSense install:

```bash
#!/bin/sh

HOST=192.168.122.100

rsync -xav --delete `dirname $0`/usr/local/www/ root@$HOST:/usr/local/www/
rsync -xav --delete `dirname $0`/etc/inc/ root@$HOST:/etc/inc/
```

# Cleaner

Before diving into a file, clean it with the supplied cleaner (`clean.sh`). This script tries to remove most of the unnecessary element attributes and does a bunch of other replaces which have to be done in every file.

# Migration conventions

All migrated files (in usr/local/www) are formatted with default [Bootstrap](http://getbootstrap.com/) components. Custom CSS goes into `usr/www/bootstrap/css/pfSense.css`, but try to keep this to a minimum.

The Bootstrap grid system is used for defining columns. We've chosen the 'small' breakpoint as the default breakpoint to collapse from a horizontal to vertical layout. You should define your column widths with `.col-sm-*`, unless there's a good (and documented ;) ) reason to deviate from this default.

## Forms

We're following a few conventions for a clean and consistent form layout:

* Every form should have at least one 'panel' which contains the form fields. If certain fields can be grouped together, you can add multiple panels to a form.
* A field consists of an outer wrapper `.form-group` which contains a `label` and the `input`
* The submit button should be placed outside of the panels to prevent confusion (e.g., the save button saves the whole form and not just the last panel).
* Checkboxes are placed within a label (see example below). The wrapping div needs an additional `.checkbox` class
* Additional field descriptions can be placed in the `.help-block` `span`

After determining the proper layout for forms we decided to create wrappers in PHP to create all forms. This de-duplicates all of the current HTML, making this migration a bit harder but any future updates infinitely easier (since all forms can be updated centrally). This is what the form-code should look like:

```php
require('classes/Form.class.php');
$form = new Form;

$section = new Form_Section('System');

$section->addInput(new Form_Input(
	'hostname',
	'Hostname',
	'text',
	$pconfig['hostname'],
	['placeholder' => 'pfSense']
))->setHelp('Name of the firewall host, without domain part');

$section->addInput(new Form_Input(
	'domain',
	'Domain',
	'text',
	$pconfig['domain'],
	['placeholder' => 'mycorp.com, home, office, private, etc.']
))->setHelp('Do not use \'local\' as a domain name. It will cause local '.
	'hosts running mDNS (avahi, bonjour, etc.) to be unable to resolve '.
	'local hosts not running mDNS.');

$form->add($section);

print $form;
```

Please make sure the referenced $_POST fields in the php-code above this code are also updated since they are automatically generated

The PHP code will output HTML something like this (with everything but relevant Bootstrap classes omitted for this example):

```html
<form class="form-horizontal">
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title">Form or panel heading</h2>
		</div>
		<div class="panel-body">
			<div class="form-group">
				<label for="input" class="col-sm-2 control-label">An input</label>
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

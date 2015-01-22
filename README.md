pfSense on bootstrap
====================

We are migrating pfSense to Bootstrap. You can help! Please respect these code-guidelines:

* use tabs (tabstop=4) for indenting (except the license-header which contains 3 lines that are indented with "\t   ")
* no trailing whitespace
* limited echoing of HTML from php, please use proper templating syntax instead (eg. foreach/endforeach)
* limited attributes on elements; _no style attributes_
* no inline javascript
* html attributes should be using double-quoted attribute-values. This means your php-code should probably use single-quoted strings
* we use icons for status-indication and buttons for actions

If you feel adventurous you can sometimes rewrite some PHP & javascript code as well; but try to keep this to a minimum.

# Cleaner

Before diving into a template, clean it with the supplied cleaner (`clean.sh`). This script tries to remove most of the unnecessary element attributes and does a bunch of other replaces which have to be done in every template.

# Template migration conventions

All migrated templates are formatted with default [Bootstrap](http://getbootstrap.com/) components. Custom CSS goes into `usr/www/bootstrap/css/pfSense.css`, but try to keep this to a minimum.

The Bootstrap grid system is used for defining columns. We've chosen the 'small' breakpoint as the default breakpoint to collapse from a horizontal to vertical layout. You should define your column widths with `.col-sm-*`, unless there's a good (and documented ;) ) reason to deviate from this default.

## Forms

* Every form should have at least one 'panel' which contains the form fields. If certain fields can be grouped together, you can add multiple panels to a form.
* A field consists out of an outer wrapper `.form-group` which contains a `label` and the `input`
* The submit button should be placed outside of the panels to prevent confusion (e.g., the save button saves the whole form and not just the last panel).

An example:

```
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
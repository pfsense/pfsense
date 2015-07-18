<?php

require_once('classes/Form.class.php');
foreach (glob('classes/Form/*.class.php') as $file)
	require_once($file);

class Modal extends Form
{
	protected $_id = '';
	protected $_title = '';
	protected $_groups = array();
	protected $_footer = array();
	protected $_html = '';
	protected $_modal_class_size = '';

	public function __construct($title, $id, $size = '')
	{
		switch ($size) {
			case 'small':
				$this->_modal_class_size = 'modal-sm';
				break;
			case 'large':
				$this->_modal_class_size = 'modal-lg';
				break;
			case '':
				break;
			default:
				throw new Exception('Incorrect size, pass either large or small');
		}
		$this->_id = $id;
		$this->_title = $title;

		$this->addClass('modal', 'fade');

		return $this;
	}

	public function add(Form_Group $group)
	{
		array_push($this->_groups, $group);
		$group->_setParent($this);

		return $group;
	}

	public function addInput(Form_Input $input)
	{
		$group = new Form_Group($input->getTitle());
		$group->add($input);

		$this->add($group);

		return $input;
	}

	public function addHtml($html)
	{
		$this->_html .= $html;

		return $this;
	}

	public function addFooter(Form_Input $input)
	{
		array_push($this->_footer, $input);

		return $input;
	}

	public function __toString()
	{
		if (empty($this->_footer)) {
			$this->addFooter(new Form_Button(
				'submit',
				'Submit'
			));

			$this->addFooter(new Form_Button(
				'close',
				'Close',
				''
			))->setAttribute('data-dismiss', 'modal');
		}

		$title = gettext($title);

		$html = implode('', $this->_groups);
		$html .= $this->_html;

		$footer = implode('', $this->_global);
		$footer .= implode('', $this->_footer);

		return <<<EOT
	<div {$this->getHtmlClass()} id="{$this->_id}" role="dialog" aria-labelledby="{$this->_id}" aria-hidden="true">
		<div class="modal-dialog {$this->_modal_class_size}">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
					<h3 class="modal-title">{$this->_title}</h3>
				</div>
				<form class="form-horizontal" action="{$this->_action}" method="post">
					<div class="modal-body">
						{$html}
					</div>
					<div class="modal-footer">
						{$footer}
					</div>
				</form>
			</div>
		</div>
	</div>
EOT;
	}
}

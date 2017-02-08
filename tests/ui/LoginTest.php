<?php
/**
 * pfsense
 *
 * @author Artur Neumann
 * @copyright 2017 Artur Neumann info@individual-it.net
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
use Test\SeleniumTestCase;
use Test\PageObject as PageObject;
class LoginTest extends SeleniumTestCase
{
	protected $loginPage;
	protected function setUp()
	{
		parent::setUp();
		$this->loginPage = new PageObject\LoginPage(
			$this->webDriver, $this->rootURL
		);
	}
	public function testNormalLogin()
	{
		$this->loginPage->loginAs("admin", "pfsense");
	}
	
	/**
	 * @expectedException Exception
	 */
	public function testFailingLogin()
	{
		$this->loginPage->loginAs("admin", "wrongpassword");
	}
}

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

namespace Test\PageObject;

use Facebook\WebDriver\WebDriverBy as WebDriverBy;
use Facebook\WebDriver\WebDriver;

class LoginPage extends PfsensePage
{
	protected $usernameLocator;
	protected $passwordLocator;
	protected $loginButtonLocator;

	public function __construct($webDriver,$url)
	{
		parent::__construct($webDriver);
		
		$this->webDriver->get($url);

		if ($this->webDriver->getTitle() !== "Login") {
		// Check that we're on the right page.
			throw new \Exception("This is not the login page");
		}
		
		/* The login page contains several HTML elements that will be 
		 * represented as WebElements.
		 * The locators for these elements should only be defined once.
		 */
		$this->usernameLocator = WebDriverBy::id("usernamefld");
		$this->passwordLocator = WebDriverBy::id("passwordfld");
		$this->loginButtonLocator = WebDriverBy::xpath(
			"//button[contains(text(),'Login')]"
		);
	}

	/**
	 * The login page allows the user to type their username into the username
	 * field
	 */
	public function typeUsername($username)
	{
		// This is the only place that "knows" how to enter a username
		$usernameField = $this->webDriver->findElement($this->usernameLocator);
		$usernameField->click();
		$usernameField->sendKeys($username);
		
		/* Return the current page object as this action doesn't navigate to a
		 * page represented by another PageObject
		 */
		return $this;
	}

	/**
	 * The login page allows the user to type their password into the
	 * password field
	 */
	public function typePassword($password)
	{
		// This is the only place that "knows" how to enter a password
		$passwordField = $this->webDriver->findElement($this->passwordLocator);
		$passwordField->click();
		$passwordField->sendKeys($password);
		/*
		 * Return the current page object as this action doesn't navigate to a
		 * page represented by another PageObject 
		 */
		return $this;
	}

	// The login page allows the user to submit the login form
	public function submitLogin()
	{
		/* This is the only place that submits the login form and expects the
		destination to be the home page.
		A separate method should be created for the instance of clicking
		login whilst expecting a login failure. */
		
		$this->webDriver->findElement($this->loginButtonLocator)->click();
		
		/* Return a new page object representing the destination. Should the
		login page ever go somewhere else (for example, a legal disclaimer)
		then changing the method signature for this method will mean that all
		tests that rely on this behaviour won't compile. */
		return new StatusDashboardPage($this->webDriver);
	}

	/* Conceptually, the login page offers the user the service of being able to
	"log into" the application using a user name and password. */
	public function loginAs($username, $password)
	{
		/* The PageObject methods that enter username, password & submit login
		have already been defined and should not be repeated here. */
		$this->typeUsername($username);
		$this->typePassword($password);
		return $this->submitLogin();
	}
}

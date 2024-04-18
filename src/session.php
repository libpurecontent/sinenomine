<?php

# Basic session class
class session
{
	# Settings
	var $defaults = array (
		'hostname'			=> 'localhost',
		'logfile'			=> false,
		'autoLogoutTime'	=> 1800,	// Seconds of inactivity after which the user will automatically be logged out
	);
	
	# Class properties
	var $databaseConnection = NULL;
	var $user = NULL;
	var $html = '';
	
	
	# Constructor
	function __construct ($settings = array (), $logout = false)
	{
		# Apply the settings/defaults
		foreach ($this->defaults as $key => $default) {
			$this->settings[$key] = (isSet ($settings[$key]) ? $settings[$key] : $default);
		}
		
		# Start the session
		session_start ();
		
		# Import posted/session variables
		if (isSet ($_POST['username'])) {
			$username = $_POST['username'];
		} else if (isSet ($_SESSION['username'])) {
			$username = $_SESSION['username'];
		}
		if (isSet ($_POST['password'])) {
			$password = $_POST['password'];
		} else if (isSet ($_SESSION['password'])) {
			$password = $_SESSION['password'];
		}
		
		# Set explicitly not to authenticate the user unless explicitly overriden
		$userIsAuthenticated = false;
		
		# Do a check against the user and password
		$message = false;
		if ((isset ($username)) && (isset ($password))) {
			
			# Authenticate against the database
			$this->databaseConnection = new database ($this->settings['hostname'], $username, $password, NULL, 'mysql', $this->settings['logfile']);
			if (!$userIsAuthenticated = $this->databaseConnection->connection) {
				
				# Show the form if the user is not in the database
				$message = 'Invalid username/password combination; please try again.';
			}
		}
		
		# Log the user out if logout explicitly requested
		if ($logout) {
			
			# Explicitly kill the session
			session_unset ();
			session_destroy ();
			$userIsAuthenticated = false;
			
			# Show the login form
			$message = 'You have been successfully logged out.';
		}
		
		# Keep the user's session alive unless inactive for the time period defined in the settings
		$timestamp = time ();
		if (isSet ($_SESSION['timestamp'])) {
			if (($timestamp - $_SESSION['timestamp']) > $this->settings['autoLogoutTime']) {
				
				# Explicitly kill the session
				session_unset ();
				session_destroy ();
				$userIsAuthenticated = false;
				
				# Define the login form message
				$minutesInactivity = round ($this->settings['autoLogoutTime'] / 60);
				$message = 'Your session expired due to ' . ($minutesInactivity <= 1 ? 'around a minute of inactivity' : "{$minutesInactivity} minutes of inactivity") . ', so you have been logged out.';
			}
		}
		
		# If for any reason the user is not authenticated, show the form and end
		if (!$userIsAuthenticated) {
			$this->loginForm ($message);
			$this->databaseConnection = NULL;
			return false;
		}
		
		# As the user is now authenticated, register (or refresh) username, password and current time as session variables.
		$_SESSION['username'] = $username;
		$_SESSION['password'] = $password;
		$_SESSION['timestamp'] = $timestamp;
		
		# Allocate the username and password to constants
		$this->user = $username;
		
		# Take the user to the same page in order to clear the form's POSTed variables and thereby prevent confusion in cases of refreshed pages
		if (isSet ($_POST['username'])) {
			$location = $_SERVER['REQUEST_URI'];
			header ('Location: ' . (isset ($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['SERVER_NAME'] . $location);
			$this->html .= "<p>You have been authenticated. <a href=\"" . htmlspecialchars ($location) . '">Please click here to continue.</a></p>';
		}
		
		# Return the authentication status
		return $userIsAuthenticated;
	}
	
	
	# Function to create a login form
	function loginForm ($message = false)
	{
		# Define login box variables
		$submitTo = preg_replace ('/\?logout$/', '', $_SERVER['REQUEST_URI']);
		$username = (isSet ($_POST['username']) ? htmlspecialchars ($_POST['username']) : '');
		$password = (isSet ($_POST['password']) ? htmlspecialchars ($_POST['password']) : '');
		
		# Problem message
		$this->html .= "\t\t" . ($message ? ('<p class="error">' . $message . '</p>') : ('<p>&nbsp;</p>'));
		
		# Construct the login box
		$this->html .= '
		<div class="graybox">
		<p>This area is for authorised users only. Please log in.</p>
		<form action="' . htmlspecialchars ($_SERVER['REQUEST_URI']) . '" method="post" name="form">
			<table border="0" cellpadding="2" cellspacing="0">
				<tr>
					<td align="right"><strong>Username:</strong></td>
					<td align="left"><input type="text" name="username" value="' . $username . '" size="15" /></td>
				</tr>
				<tr>
					<td align="right"><strong>Password:</strong></td>
					<td align="left"><input type="password" name="password" value="' . $password . '" size="15" /></td>
				</tr>
				<tr>
					<td align="right">&nbsp;</td>
					<td align="left"><input type="submit" class="button" value="Login" accesskey="s" /></td>
				</tr>
			</table>
		</form>
		</div>';
	}
	
	
	/*
	#!# Need to refactor the constructor to enable logout to take place after the constructor has been called
	# Function to provide logout functionality
	function logout ()
	{
		
	}
	*/
	
	
	# Function to return the database connection
	function getDatabaseConnection ()
	{
		# Return the connection
		return $this->databaseConnection;
	}
	
	
	# Function to return the username
	function getUser ()
	{
		# Return the username
		return $this->user;
	}
	
	
	# Function to return the HTML
	function getHtml ()
	{
		# Return the username
		return $this->html;
	}
}

?>

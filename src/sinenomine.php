<?php

#!# Joins to empty tables seem to revert to a standard input field rather than an empty SELECT list
#!# Need to test data object editing version
#!# How are unknown field types dealt with?
#!# Password field is not editable, e.g. http://sinenomine.geog.cam.ac.uk/alumni/contacts/add.html
#!# Timestamp is not visible when intelligence switched on
#!# Ensure that BINARY values are not put into the HTML
#!# User administration section
#!# Need a nice API like $sinenomine->database->field->orderby('date');



# Class to deal with generic table editing; called 'sineNomine' which means 'without a name' in recognition of the generic use of this class
class sinenomine
{
	# Class variables
	private $database = NULL;
	private $table = NULL;
	private $record = NULL;
	private $databaseConnection = NULL;
	private $user = NULL;
	private $credentialsUser = false;
	private $data = NULL;	// The data that can be retrieved from >process ()
	private $key = false;
	private $html = '';
	private $mainHtml = '';
	private $includeOnly = array ();
	private $exclude = array ();
	private $attributes = array ();
	private $orderby = array ();
	private $direction = array ();
	private $validation = array ();
	
	
	# Specify available arguments as defaults or as NULL (to represent a required argument)
	private $defaults = array (
		'hostname' => 'localhost',	// Whether to use internal logins
		'vendor'	=> 'mysql',
		'username'	=> false,	// Pre-supplied username
		'password'	=> false,	// Pre-supplied password
		'credentials' => array (),	// Credentials listings, as URL => file containing username and password; NB this is not database => $file because this assumes credentials are being passed on a Raven-protected page
		'autoLogoutTime' => 1800,	// Number of seconds after which automatic logout will take place
		'database' => false,
		'table' => false,
		'tableUrlMoniker' => false,	// When forcing a table, enables a URL moniker instead of the table name itself
		'record' => false,	// Force to a specific record ID
		'administrators' => array (),	// List of administrators
		'userIsAdministrator' => false,	// Whether the user is an administrator (overrides list of administrators)
		'baseUrl' => false,
		'do' => 'do',	// $_GET['do'] or something else for the main action, e.g. 'action' would look at $_GET['action']; 'do' is the default as it is less likely to clash
		'action' => false,		// Forced action (e.g. index/edit/delete) - like 'do' but a direct value rather than a reference to a $_GET field
		'databaseUrlPart' => false,	// Whether to include the database in the URL *if* a database has been supplied in the settings
		'hostnameInTitle' => false,	// Whether to include the hostname in the <title> tag hierarchy
		//'administratorEmail'	=> $_SERVER['SERVER_ADMIN'], /* Defined below */	// Who receives error notifications (or false if disable notifications)
		'gui' => false,	// Whether to add GUI features rather than just be an embedded component
		'headerHtml' => false,	// Specific header HTML (rather than the default); ignored in non-GUI mode
		'footerHtml' => false,	// Specific footer HTML (rather than the default); ignored in non-GUI mode
		'headingLevel' => 1,	// Heading level for main title
		'showMetadata' => true,	// Whether the metadata fields at the top of table view are visible
		'excludeMetadataFields' => array ('Field', /*'Collation', 'Default',*/'Privileges'),
		'commentsAsHeadings' => true,	// Whether to use comments as headings if there are any comments
		'convertJoinsInView' => true,	// Whether to convert joins when viewing a table
		'clonePrefillsSourceKey' => true,	// Whether cloning should prefill the source record's key
		'displayErrorDebugging' => false,	// Whether to show error debug info on-screen
		'highlightMainTable'	=> true,	// Whether to make bold a table whose name is the same as the database
		'listingsShowTotals'	=> true,	// Whether to show the total number of records in each table when listings
		'attributes' => array (),
		'orderby' => array (),	// Orderby default for a specific area
		'direction' => array (),	// Direction default for a specific area
		'exclude' => array (),
		'validation' => array (),
		'deny' => false,	// Deny edit access to database(s)/table(s)
		'denyInformUser' => true,	// Whether to inform the user if a database/table is denied
		'denyAdministratorOverride' => true,	// Whether to allow administrators access to denied database(s)/table(s)
		'includeOnly' => array (),
		'attributesSettingsPlaceholders' => true,	// Whether settings in attributes should have placeholder replacement
		'nullText' => '',		// ultimateForm defaults
		'size' => 30,			// ultimateForm defaults
		'cols' => 60,			// ultimateForm defaults
		'rows' => 5,			// ultimateForm defaults
		'datePicker' => false,	// ultimateForm defaults
		'int1ToCheckbox' => false,
		'unsavedDataProtection' => true,
		'simpleJoin' => false,
		'lookupFunctionParameters' => array (),
		'refreshSeconds' => 0,	// Refresh time in seconds after editing an article
		'successfulRecordRedirect' => false,	// Whether to redirect to a successfully-submitted (created/edited) record and show a flash
		'showViewLink' => false,	// Whether to show the redundant 'view' link in the record listings
		'compressWhiteSpace' => true,	// Whether to compress whitespace between table cells in the HTML
		'reserved' => array ('cluster', 'information_schema', 'mysql', 'performance_schema', 'phpmyadmin', 'sys'),
		'logfile' => false,
		'application' => false,	// Name of a calling application
		'intelligence' => true,	// Whether to enable dataBinding intelligence
		'pagination' => 100,	// Whether to enable pagination, and if so, maximum records per max
		'paginationRedirect'	 => true,	// Whether to enable redirects on selecting a page that does not exist (e.g. 10 pages, page 11 selected results in redirection to page 10)
		'rewrite' => true,	// Whether mod_rewrite is on
		'phpmyadmin' => false,	// The base of a PhpMyAdmin instance if links to equivalent pages are wanted
		'queryTerm'	=> 'q',
		'hideTableIntroduction'	=> false,	// Hide text "This table, X.Y., contains ..." and "You can [+ add a record]"
		'hideSearchBox' => false,			// Hide search box above main table
		'hideExport' => false,				// Hide export table button
		'fieldFiltering' => false,	// Whether to enable the field filtering interface; string database.table.field for storage of the user data
		'fieldFilteringCheckboxesTruncate' => 25,	// Whether to truncate field filtering checkboxes text
		'tableCommentsInSelectionList' => false,	// Whether the table comments should be shown in a table selection list
		'tableCommentsInSelectionListOnly' => false,	// Whether the table comments should be shown in a table selection list only, i.e. rather than the table name itself
		'formDiv' => 'graybox lines',
		'richtextEditorBasePath'			=> '/_ckeditor/',					# Global default setting for of the editor files
		'richtextEditorToolbarSet'			=> 'pureContent',					# Global default setting for richtext editor toolbar set
		'richtextEditorAreaCSS'				=> '',								# Global default setting for richtext editor CSS
		'richtextWidth'						=> '100%',							# Global default setting for richtext width; assumed to be px unless % specified
		'richtextHeight'					=> 400,								# Global default setting for richtext height; assumed to be px unless % specified
		'richtextEditorFileBrowser'			=> '/_ckfinder/',					# Global default setting for richtext file browser path (must have trailing slash), or false to disable
		'richtextEditorConfig.docType'		=> '<!DOCTYPE html>',				# Global default setting for richtext config.docType
		'submitButtonPosition' => 'both',
		'callback'	=> array (),	// Whether to register a callback class+method for a specific database/table/action, specified as a nested array
		'constraint' => array (),	// Whether to register an SQL extract constraint for a specific database/table/action, specified as a nested array
		'moveDeleteToEnd' => false,	// Whether to show the Delete link in the index view at the end (defaults to showing alongside the Edit/Clone buttons)
		'truncateValues' => false,	// In table display mode, whether to truncate values in the main data table, and if so by how many characters
		'autofocus' => false,
	);
	
	
	# Specify available actions
	private $actions = array (
		'logout' => array (
			'description' => 'Logout',
			'url' => '/logout.html',
			'urlQueryString' => '/?%do=logout',
		),
		'index' => array (
			'description' => 'View records',
		),
		'listing' => array (
			'description' => 'Quick listing of all records',
		),
		'record' => array (
			'description' => 'View a record',
		),
		'add' => array (
			'description' => 'Add a record',
		),
		'edit' => array (
			'description' => 'Edit a record',
		),
		'export' => array (
			'description' => 'Export a table',
		),
		'clone' => array (
			'description' => 'Clone a record',
		),
		'delete' => array (
			'description' => 'Delete a record',
		),
		'search' => array (
			'description' => 'Search within records',
		),
		'structure' => array (
			'description' => 'Database structure hierarchy',
			'administration' => true,
			'url' => '/structure.html',
			'urlQueryString' => '/?%do=structure',
		),
		'overflow' => array (
			'description' => 'Integer fields nearing overflow',
			'administration' => true,
		),
	);
	
	
	# Constructor
	public function __construct ($settings = array (), $databaseConnection = NULL, &$html = NULL)
	{
		# Load required libraries
		require_once ('application.php');
		require_once ('database.php');
		require_once ('pagination.php');
		require_once ('pureContent.php');
		// session.php is loaded below, as it depends on settings which are dependent on application.php
		
		# Add additional defaults
		$this->defaults['administratorEmail'] = $_SERVER['SERVER_ADMIN'];
		//$this->defaults['application'] = __CLASS__;
		
		# Start the HTML
		$this->html  = $html;
		
		# Parse pre-supplied credentials files settings to inject these into the main settings
		$errors = array ();	// Hack to cache this until the settings have been assigned, so that >error() doesn't produce offsets
		if (isSet ($settings['credentials']) && $settings['credentials'] && is_array ($settings['credentials'])) {
			foreach ($settings['credentials'] as $urlRegexp => $credentialsFile) {
				if (preg_match ('@' . $urlRegexp . '@', $_SERVER['REQUEST_URI'])) {
					if (is_readable ($credentialsFile)) {
						include ($credentialsFile);
						if (isSet ($credentials)) {
							$settings = array_merge ($settings, $credentials);
							$this->credentialsUser = true;
						} else {
							$errors[] = 'A credentials file was specified but did not contain syntactically-correct credentials settings.';
						}
					} else {
						$errors[] = 'A credentials file was specified but could not be read.';
					}
				}
			}
		}
		
		# Merge in the arguments; note that $errors returns the errors by reference and not as a result from the method
		if (!$this->settings = application::assignArguments ($errors, $settings, $this->defaults, __CLASS__, NULL, $handleErrors = true)) {
			return false;
		}
		
		# Assign the base URL
		$this->baseUrl = ($this->settings['baseUrl'] ? ($this->settings['baseUrl'] == '/' ? '' : $this->settings['baseUrl']) : application::getBaseUrl ());
		
		# Determine the action to take, using the default (index) if none supplied
		$this->action = (!isSet ($_GET[$this->settings['do']]) ? 'index' : (array_key_exists ($_GET[$this->settings['do']], $this->actions) ? $_GET[$this->settings['do']] : NULL));
		if ($this->settings['action']) {
			$this->action = $this->settings['action'];	// Overrides any $_GET['do']
		}
		
		# Define a logout URL and Determine whether to log out
		$this->logoutUrl = $this->baseUrl . ($this->settings['rewrite'] ? $this->actions['logout']['url'] : str_replace ('%do', $this->settings['do'], $this->actions['logout']['urlQueryString']));
		$logout = ($this->action == 'logout');
		
		# Define other URLs
		$this->structureUrl = $this->baseUrl . ($this->settings['rewrite'] ? $this->actions['structure']['url'] : str_replace ('%do', $this->settings['do'], $this->actions['structure']['urlQueryString']));
		
		# If credentials are supplied, use these in preference to session creation
		if ($this->settings['hostname'] && $this->settings['username'] && $this->settings['password']) {
			$this->databaseConnection = new database ($this->settings['hostname'], $this->settings['username'], $this->settings['password'], NULL, 'mysql', $this->settings['logfile']);
			if (!$this->databaseConnection->connection) {
				$this->databaseConnection = NULL;
				$this->mainHtml = $this->error ('No valid database connection was supplied.');
				// $this->databaseConnection->reportError ($this->defaults['administratorEmail'], 'sineNomine');
			}
			$this->user = $_SERVER['REMOTE_USER'];
		} else {
			
			# In GUI mode, start a session to obtain credentials dynamically
			if ($this->settings['gui'] && $databaseConnection === NULL) {
				require_once ('session.php');
				$session = new session ($this->settings, $logout);
				
				# Redirect to the front page if logged out, having destroyed the session
				if ($logout) {
					header ('Location: http://' . $_SERVER['SERVER_NAME'] . $this->baseUrl);
					$this->mainHtml = "<p>You have been logged out. " . $this->createLink (NULL, NULL, NULL, NULL, 'Please click here to continue.') . '</p>';
				} else {
					$this->databaseConnection = $session->getDatabaseConnection ();
					$this->user = $session->getUser ();
					$this->mainHtml = $session->getHtml ();
				}
				
			# Otherwise create a connection
			} else {
				if (!$databaseConnection || !$databaseConnection->connection) {
					$this->databaseConnection = NULL;
					$this->mainHtml = $this->error ('No valid database connection was supplied.');
				} else {
					$this->databaseConnection = $databaseConnection;
				}
			}
		}
		
		# Determine if the user is an administrator
		$this->userIsAdministrator = ($this->settings['userIsAdministrator'] || ($this->user && in_array ($this->user, $this->settings['administrators'])));
		
		# Set up all the logic and then cache the main page HTML
		if ($this->databaseConnection) {
			$this->mainHtml = $this->main ();
		}
		
		# Create a link to the equivalent PHPMyAdmin page
		$this->phpMyAdminUrl = NULL;
		if ($this->userIsAdministrator && $this->settings['phpmyadmin']) {
			$this->phpMyAdminUrl = $this->settings['phpmyadmin'];
			if ($this->database) {
				if ($this->table) {
					$this->phpMyAdminUrl .= 'tbl_structure.php?goto=tbl_structure.php&amp;db=' . $this->doubleEncode ($this->database) . '&amp;table=' . $this->doubleEncode ($this->table);
				} else {
					$this->phpMyAdminUrl .= '?db=' . $this->doubleEncode ($this->database);
				}
			}
		}
	}
	
	
	# Function which activates the processing, which must be called by the user
	public function process ()
	{
		# Take action
		if ($this->databaseConnection && $this->action) {
			$this->mainHtml .= $this->{$this->action} ();
		}
		
		# Surround the mainHtml with a div
		if ($this->settings['gui']) {$this->mainHtml = "\n\n\n\t<div id=\"content\">\n\n" . $this->mainHtml . "\n\t</div>";}
		
		# Build the HTML
		$this->html .= $this->pageHeader ();
		$this->html .= $this->pageMenu ();
		$this->html .= $this->mainHtml;
		$this->html .= $this->pageFooter ();
		
		# In GUI mode, show the HTML directly
		if ($this->settings['gui']) {
			echo $this->html;
		}
		
		# Return the raw data as an array
		return $this->data;
	}
	
	
	# Function to accept attributes settings in the dataBinding
	public function attributes ($database = NULL, $table = NULL, $field = NULL, $settings = NULL)
	{
		# Replace settings with placeholders
		if ($this->settings['attributesSettingsPlaceholders'] && $settings && is_array ($settings)) {
			foreach ($settings as $key => $value) {
				if (is_string ($value) && substr_count ($value, '%table')) {
					$settings[$key] = str_replace ('%table', $this->table, $value);
				}
			}
		}
		
		# Register the parameters
		$this->register (__FUNCTION__, $database, $table, $field, $settings);
	}
	
	
	# Function to accept a default ordering for the table (overridablevia query string)
	public function orderby ($database = NULL, $table = NULL, $orderby = NULL)
	{
		# Disallow if not a string
		if (!is_string ($orderby)) {return false;}
		
		# Register the parameters
		$this->register (__FUNCTION__, $database, $table, $orderby);
	}
	
	
	# Function to accept direction settings (overridable via query string)
	public function direction ($database = NULL, $table = NULL, $direction = NULL)
	{
		# Disallow if not a string
		if (!is_string ($direction)) {return false;}
		
		# Convert to uppercase
		$direction = strtolower ($direction);
		
		# Register the value; ASC is not wrong but pointless as it is the default so is not set
		if ($direction == 'desc') {
			$this->register (__FUNCTION__, $database, $table, $direction);
		}
	}
	
	
	# Function to accept includeOnly settings in the dataBinding
	public function includeOnly ($database = NULL, $table = NULL, $fields = NULL)
	{
		# Register the parameters
		$this->register (__FUNCTION__, $database, $table, $fields);
	}
	
	
	# Function to accept exclude settings in the dataBinding
	public function exclude ($database = NULL, $table = NULL, $fields = NULL)
	{
		# Register the parameters
		$this->register (__FUNCTION__, $database, $table, $fields);
	}
	
	
	# Function to deal with registering overrides
	private function register ($function, $database, $table, $field, $settings = NULL)
	{
		# Check parameters are all supplied
		$setupOk = true;
		if (!$database) {$this->html .= "<p>A database was not supplied</p>"; $setupOk = false;}
		if (!$table) {$this->html .= "<p>A table was not supplied</p>"; $setupOk = false;}
		if (!$field) {$this->html .= "<p>A fieldname was not supplied</p>"; $setupOk = false;}
		if (($function == 'attributes') && (!$settings || !is_array ($settings))) {$this->html .= "<p>A set of settings was not supplied</p>"; $setupOk = false;}
		
		# End if parameters are wrong
		if (!$setupOk) {
#!# Throw error
		}
		
		# Add the item to the registry if it matches the database and table
		if ((($this->database == $database) || $database == '*') && (($this->table == $table) || ($table == '*'))) {
			if ($function == 'attributes') {
				$this->{$function}[$field] = $settings;
			} else {
				$this->{$function}[] = $field;
			}
		}
	}
	
	
	
	# Function to set up the environment and take action
	private function main ()
	{
		# Start the HTML
		$html  = '';
		
		# Ensure any deny list is an array
		$this->settings['deny'] = application::ensureArray ($this->settings['deny']);
		
		# Remove the deny list if the user has suitable privileges
		if ($this->userIsAdministrator && $this->settings['denyAdministratorOverride']) {
			$this->settings['deny'] = false;
		}
		
		# Provide encoded versions of particular class variables for use in pages
		$this->hostname = $this->databaseConnection->hostname;
		$this->hostnameEntities = htmlspecialchars ($this->hostname);
		
		# End if no valid action is specified
		if (!$this->action) {
			$html .= "\n<p>No valid action was specified.</p>";
			return $html;
		}
		
		# Show title
		$html = "\n<h{$this->settings['headingLevel']}>" . htmlspecialchars ($this->actions[$this->action]['description']) . "</h{$this->settings['headingLevel']}>";
		
		# Determine whether links should include the database URL part
		$this->includeDatabaseUrlPart = (!$this->settings['database'] || ($this->settings['database'] && $this->settings['databaseUrlPart']));
		
		# Get the available databases
		#!# Add runtime hidden databases configurability
		$this->databases = $this->databaseConnection->getDatabases ($this->settings['reserved']);
		
		# Make a list of denied databases and remove them from the list of available databases
		$deniedDatabases = array ();
		if ($this->settings['deny'] && is_array ($this->settings['deny'])) {
			foreach ($this->settings['deny'] as $deniedDatabase) {
				if (!is_array ($deniedDatabase)) {
					$deniedDatabases[] = $deniedDatabase;
				}
			}
			if ($deniedDatabases) {
				$this->databases = array_diff ($this->databases, $deniedDatabases);
			}
		}
		
		# Allocate the databases list as the processing output
		$this->data = $this->databases;
		
		# End if no editable databases
		if (!$this->databases) {
			$html .= "\n<p>There are no databases" . (($this->settings['denyInformUser'] && $deniedDatabases) ? ' that you can edit' : '') . " in the system, so this editor cannot be used.</p>";
			$this->action = NULL;
			return $html;
		}
		
		# Run specific functions
		if (isSet ($this->actions[$this->action]['administration'])) {
			$html .= $this->{$this->action} ();
			$this->action = NULL;
			return $html;
		}
		
		# Ensure a database is supplied
		if (!$this->settings['database'] && !isSet ($_GET['database'])) {
			$html .= "\n<p>Please select a database:</p>";
			$html .= $this->linklist ($this->databases);
			$this->action = NULL;
			return $html;
		}
		
		# Allocate the database, preferring settings over user-supplied data
		$this->database = ($this->settings['database'] ? $this->settings['database'] : $_GET['database']);
		
		# Tell the user if the current database is denied
		if ($this->settings['denyInformUser'] && in_array ($this->database, $deniedDatabases)) {
			$html .= sprintf ("\n<p>Access to the database <em>%s</em> has been denied by the administrator.</p>", htmlspecialchars ($this->database));
			$this->database = NULL;
			$this->action = NULL;
			return $html;
		}
		
		# Ensure the database exists
		if (!in_array ($this->database, $this->databases)) {
			$this->database = NULL;
			$html .= "\n<p>There is no such database. Please select one:</p>";
			$html .= $this->linklist ($this->databases);
			$this->action = NULL;
			return $html;
		}
		
		# Provide encoded versions of the database class variable for use in links
		$this->databaseEncoded = $this->doubleEncode ($this->database);
		$this->databaseEntities = htmlspecialchars ($this->database);
		
		# Get the available tables for this database
		$this->tables = $this->databaseConnection->getTables ($this->database);
		
		# Get the table comments
		$this->tableComments = $this->getTableComments ($this->database);
		
		# Make a list of denied tables in this database and remove them from the list of available tables
		$deniedTables = array ();
		if ($this->settings['deny'] && is_array ($this->settings['deny']) && isSet ($this->settings['deny'][$this->database]) && $this->settings['deny'][$this->database]) {
			$this->settings['deny'][$this->database] = application::ensureArray ($this->settings['deny'][$this->database]);
			foreach ($this->settings['deny'][$this->database] as $deniedTable) {
				if (!is_array ($deniedTable)) {
					$deniedTables[] = $deniedTable;
				}
			}
			if ($deniedTables) {
				$this->tables = array_diff ($this->tables, $deniedTables);
			}
		}
		
		# Allocate the tables list as the processing output
		$this->data = $this->tables;
		
		# Get the available tables for this database
		if (!$this->tables) {
			$html .= "\n<p>There are no tables" . (($this->settings['denyInformUser'] && $deniedTables) ? ' that you can edit' : '') . " in this database.</p>";
			$this->action = NULL;
			return $html;
		}
		
		# Determine a link to database level
		$this->databaseLink = $this->createLink ($this->database, NULL, NULL, NULL, NULL, NULL, NULL, NULL, false);
		
		# Ensure a table is supplied
		if (!$this->settings['table'] && !isSet ($_GET['table'])) {
			$tables = $this->tables;
			if ($this->settings['tableCommentsInSelectionList']) {
				$tables = array ();
				foreach ($this->tables as $index => $table) {
					$tables[$table] = (isSet ($this->tableComments[$table]) && strlen ($this->tableComments[$table]) ? $this->tableComments[$table] : $table);
				}
			}
			$html .= "\n<p>Please select a table (or add [+] a record):</p>";
			asort ($tables);
			$html .= $this->linklist ($this->database, $tables, false, $addAddLink = true, $this->settings['listingsShowTotals']);
			$this->action = NULL;
			return $html;
		}
		
		# Allocate the table, preferring settings over user-supplied data
		$this->table = ($this->settings['table'] ? $this->settings['table'] : $_GET['table']);
		
		# Tell the user if the current database is denied
		if ($this->settings['denyInformUser'] && in_array ($this->table, $deniedTables)) {
			$html .= "\n<p>Access to the table <em>" . htmlspecialchars ($this->table) . '</em> in the database <em>' . $this->createLink ($this->database) . '</em> has been denied by the administrator.</p>';
			$this->table = NULL;
			$this->action = NULL;
			return $html;
		}
		
		# Ensure the table exists
		if (!in_array ($this->table, $this->tables)) {
			$this->table = NULL;
			$html .= "\n<p>There is no such table. Please select one:</p>";
			$html .= $this->linklist ($this->database, $this->tables);
			$this->action = NULL;
			return $html;
		}
		
		# Provide encoded versions of the table class variable for use in links
		$this->tableEncoded = $this->doubleEncode ($this->table);
		$this->tableEntities = htmlspecialchars ($this->table);
		
		# Determine a link to table level
		$this->tableLink = $this->createLink ($this->database, $this->table, NULL, NULL, NULL, NULL, NULL, NULL, false);
		
		# Get table status
		$this->tableStatus = $this->databaseConnection->getTableStatus ($this->database, $this->table);
		
		# Get the fields for this table
		if (!$this->fields = $this->databaseConnection->getFields ($this->database, $this->table, true)) {
			$this->action = NULL;
			return $html .= $this->error ('There was some problem getting the fields for this table.');
		}
		
		# Get the joins for this table and add them into the fields list as well as creating an array of join data
		$this->joins = array ();
		foreach ($this->fields as $fieldname => $fieldAttributes) {
			$simpleJoin = ($this->settings['simpleJoin'] ? array ($this->database, $this->table, $this->tables) : false);
			if ($matches = database::convertJoin ($fieldAttributes['Field'], $simpleJoin)) {
				$this->joins[$fieldname] = $matches;
				$this->fields[$fieldname]['_field'] = $matches['field'];
				$this->fields[$fieldname]['_targetDatabase'] = $matches['database'];
				$this->fields[$fieldname]['_targetTable'] = $matches['table'];
			}
		}
		
		# Get the headings for the table (field comment names)
		$this->headings = $this->databaseConnection->getHeadings ($this->database, $this->table, $this->fields, $useFieldnameIfEmpty = true, $this->settings['commentsAsHeadings']);
		
		# Get the unique field
		#!# Is this error condition necessary?
		if (!$this->key = $this->databaseConnection->getUniqueField ($this->database, $this->table, $this->fields)) {
			$this->action = NULL;
			return $html .= $this->error ('This table appears not to have a unique key field.');
		}
		
		# Determine if the key is automatic (true/false, or NULL if no key)
		$this->keyIsAutomatic = ($this->key ? ($this->fields[$this->key]['Extra'] == 'auto_increment') : NULL);
		
		# Get record data
		$this->record = false;
		$this->recordEntities = false;
		$this->recordLink = false;
		$this->data = array ();
		$recordId = ($this->settings['record'] ? $this->settings['record'] : (isSet ($_GET['record']) ? $_GET['record'] : false));
		if ($recordId) {
			#!# Still says 'view records' as the main title
			if ($this->action == 'index') {$this->action = 'record';}
			if (!$this->data = $this->databaseConnection->selectOne ($this->database, $this->table, array ($this->key => $recordId))) {
				if ($this->action != 'add') {
					$html .= "\n<p>There is no such record <em>" . htmlspecialchars ($recordId) . '</em>. Did you intend to ' . $this->createLink ($this->database, $this->table, NULL, 'add', 'create a new record' . ($this->keyIsAutomatic ? '' : ' with that key'), 'action button add') . '?</p>';
					$this->action = NULL;
					return $html;
				}
			} else {
				$this->record = $recordId;
				$this->recordEntities = htmlspecialchars ($this->record);
				$this->recordLink = $this->createLink ($this->database, $this->table, $this->record, NULL, NULL, NULL, NULL, NULL, false);
			}
		}
		
		# Adjust action for clone
		if ($this->action == 'clone') {$this->action = 'cloneRecord';}	// 'clone' can't be used as a function name
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to return the HTML
	public function getHtml ()
	{
		# Return the HTML
		return $this->html;
	}
	
	
	# Function to create a breadcrumb trail
	private function breadcrumbTrail ()
	{
		# End if there is no database connection
		if (!$this->databaseConnection) {return false;}
		
		# End if not required
		if (!$this->settings['gui']) {return false;}
		
		# Construct the list of items, avoiding linking to the current page
		$items[] = $this->createLink ();
		if ($this->database) {$items[] = (($this->action == 'index' && !$this->table) ? "<span title=\"Database\">{$this->databaseEntities}</span>" : $this->createLink ($this->database));}
		if ($this->table) {$items[] = ($this->action == 'index' ? "<span title=\"Table\">{$this->tableEntities}</span>" : $this->createLink ($this->database, $this->table)) . ($this->tableStatus['Comment'] ? ' <span class="comment"><em>(' . htmlspecialchars ($this->tableStatus['Comment']) . ')</em></span>' : '');}
		if ($this->record) {$items[] = ($this->action == 'record' ? "<span title=\"Record\">{$this->recordEntities}</span>" : $this->createLink ($this->database, $this->table, $this->record));}
		
		# Compile the HTML
		$html = "<p class=\"locationline\">You are in: " . implode (' &raquo; ', $items) . '</p>';
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to return the current database/table/record hierarchy position as text
	private function position ($hostname = true, $addPrefix = ': ', $convertEntities = true)
	{
		# End if there is no database connection
		if (!$this->databaseConnection) {return false;}
		
		# Compile the list
		$items = array ();
		if ($this->hostname) {$items[] = ($convertEntities ? $this->hostnameEntities : $this->hostname);}
		if ($this->database) {$items[] = ($convertEntities ? $this->databaseEntities : $this->database);}
		if ($this->table) {$items[] = ($convertEntities ? $this->tableEntities : $this->table);}
		if ($this->record) {$items[] = ($convertEntities ? $this->recordEntities : $this->record);}
		
		# Compile the list
		$html  = ($addPrefix ? $addPrefix : '') . implode (' &raquo; ', $items);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create the GUI-mode menu
	private function pageMenu ()
	{
		# End if there is no database connection
		if (!$this->databaseConnection) {return false;}
		
		# End if not in GUI mode
		if (!$this->settings['gui']) {return false;}
		
		# Create a list of databases
		$html = $this->linklist ($this->databases, (isSet ($this->tables) ? $this->tables : NULL), $this->database, false, $this->settings['listingsShowTotals']);
		
		# Surround the HTML with a menu div
		$html = "\n\t<div id=\"menu\">" . $html . "\n\t</div>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create a list of links
	private function linklist ($databases, $tables = NULL, $current = false, $addAddLink = false, $listingsShowTotals = false, $tabs = 1)
	{
		# Determine which is being looped through (databases or tables)
		$isDatabases = (is_array ($databases));
		$items = ($isDatabases ? $databases : $tables);
		
		# Create the links
		$list = array ();
		foreach ($items as $indexOrKey => $value) {
			
			# Determine the real name of the item (e.g. the table name) and the label
			$item = $value;
			$label = $value;
			if (!$isDatabases && $this->settings['tableCommentsInSelectionList']) {
				$item = $indexOrKey;
				if ($this->settings['tableCommentsInSelectionListOnly']) {
					$label = $value;
				} else {
					$label = "{$value} [{$item}]";
				}
			}
			
			# Define the link for the current item
			if (is_array ($databases)) {
				$class = ($item == $this->database ? 'current' : false);
				$link = $this->createLink ($item, NULL, NULL, NULL, NULL, $class);
			} else {
				# Show the number of records in the table if wanted
				#!# Ideally the bracketed section would have a span round it for styling
				$total = ((is_array ($tables) && $listingsShowTotals) ? ' (' . $this->databaseConnection->getTotal ($databases, $item) . ')' : false);
				
				$class  = ($item == $this->table ? 'current' : false);
				if ($this->settings['highlightMainTable'] && ($item == $this->database)) {$class .= ($class ? ' ' : '') . 'maintable';}
				$link = $this->createLink ($databases, $item, NULL, NULL, $label . $total, NULL, NULL, $class);
			}
			
			# Add an [+] link afterwards if wanted
			if (is_array ($tables) && $addAddLink) {$link .= ' &nbsp;' . $this->createLink ($databases, $item, NULL, 'add', '[+]');}
			
			# Deal with nesting, i.e. if lists of both databases and tables are supplied, add the tables list at the 'current' item
			if (is_array ($databases) && is_array ($tables) && ($item == $current)) {
				$link .= $this->linkList ($item, $tables, $current, $addAddLink, $listingsShowTotals, ($tabs + 1));
			}
			
			# Add the item to the list
			$list[] = $link;
		}
		
		# Create the list
		$html = application::htmlUl ($list, $tabs);
		
		# Return the list
		return $html;
	}
	
	
	# Function to list an index of all records in a table, i.e. only the keys
	private function index ($fullView = true, $data = false)
	{
		# Start the HTML
		$html = '';
		
		# Determine the ordering, using a URL-supplied value if the fieldname exists
		$orderBy = $this->key;
		#!# The apiOverrideInUseOrderBy needs to be used; at present it means that setting >orderby removes the ordering links for the $this->key field
		$apiOverrideInUseOrderBy = false;
		if (isSet ($_GET['orderby']) && array_key_exists ($_GET['orderby'], $this->fields)) {	// NB a _GET['orderby'] that is not valid is ignored so will not override an API-supplied orderby
			$orderBy = $_GET['orderby'];
		} else if ($this->settings['orderby'] && array_key_exists ($this->table, $this->settings['orderby'])) {	// i.e. if no URL value is specifically requested by the user but an 'orderby' is set by the API
			$orderBy = $this->settings['orderby'][$this->table];
			$apiOverrideInUseOrderBy = true;	// Used to enable orderby=id in the link, which otherwise is never generated
		} else if ($this->orderby && array_key_exists ($this->orderby[0], $this->fields)) {	// i.e. if no URL value is specifically requested by the user but an 'orderby' is set by the API
			$orderBy = $this->orderby[0];
			$apiOverrideInUseOrderBy = true;	// Used to enable orderby=id in the link, which otherwise is never generated
		}
		
		# Determine the direction
		$descending = false;
		$apiOverrideInUseDescending = false;
		if (isSet ($_GET['direction'])) {
			if ($_GET['direction'] == 'desc') {
				$descending = true;
			}
		} else if ($this->direction) {	// i.e. if no URL value is specifically requested by the user but a 'direction' is set by the API
			if ($this->direction[0] == 'desc') {
				$descending = true;
				$apiOverrideInUseDescending = true;	// Used to enable direction=asc in the link, which otherwise is never generated
			}
		}
		$direction = ($descending ? ' DESC' : '');
		
		# Add constraint, which overrides all others, if required
		$constraintsSql = '';
		if (isSet ($this->settings['constraint'][$this->database]) && isSet ($this->settings['constraint'][$this->database][$this->table])) {
			$constraintsSql = 'WHERE ' . $this->settings['constraint'][$this->database][$this->table];
		}
		
		# Add specific sorting based on name hinting
		$orderBySql = $orderBy;
		if ($orderBy == 'ipaddress') {
			$orderBySql = 'INET_ATON(ipaddress)';
		}
		
		# Compile the SQL for ORDER BY
		$orderBySql = "{$orderBySql}{$direction}" . (($orderBy != $this->key) ? ",{$this->key}" : '');
		
		# Determine whether to use pagination
		$usePagination = ($this->settings['pagination'] && $fullView);
		
		# Apply pagination if necessary
		$page = NULL;
		$allRecords = (isSet ($_GET['page']) && ($_GET['page'] == 'all'));
		$pageInUse = NULL;
		$paginationSql = '';
		$paginationHtml = '';
		
		$viewPageHtml = '';
		if ($usePagination) {
			
			# Get a count of the data for pagination purposes
			if (!$totalRecords = $this->databaseConnection->getTotal ($this->database, $this->table, $constraintsSql)) {
				$html .= "\n<p>There are no records in the <em>{$this->tableEntities}</em> table.</p>\n<p>You can " . $this->createLink ($this->database, $this->table, NULL, 'add', 'add a record', 'action button add') . '.</p>';
				return $html;
			}
			
			# Calculate pagination
			$page = ((isSet ($_GET['page']) && is_numeric ($_GET['page'])) ? $_GET['page'] : 1);
			$pageInUse = ($allRecords ? 'all' : $page);
			$pageOriginal = $page;
			list ($totalPages, $offset, $totalRecords, $limit, $page) = pagination::getPagerData ($totalRecords, $this->settings['pagination'], $page);
			
			# Assemble the pagination SQL, if page is not 'all'
			if (!$allRecords) {
				$paginationSql = " LIMIT {$offset},{$limit}";
			}
			
			# Assemble the pagination HTML
			if ($totalPages > 1) {
				
				# Maintain order by and direction
				$linkArguments = array ();
				if ($descending) {$linkArguments['direction'] = 'desc';}
				if ($orderBy != $this->key) {$linkArguments['orderby'] = $orderBy;}
				
				# Redirect the user to the nearest page number if the requested page does not exist
				if ($page != $pageOriginal) {
					$redirectTo = $this->createLink ($this->database, $this->table, NULL, NULL, NULL, NULL, $page, $linkArguments, $asHtml = false);
					if ($this->settings['paginationRedirect']) {
						application::sendHeader (301, $_SERVER['_SITE_URL'] . $redirectTo);
					}
					$html .= "<p>Please click here to proceed to " . $this->createLink ($this->database, $this->table, NULL, NULL, "page {$page}", 'action button list', $page, $linkArguments) . " of the record listing for " . $this->createLink ($this->database, $this->table, NULL, NULL, NULL, 'action button list') . ". (page {$pageOriginal} does not exist).</p>";
					return $html;
				}
				
				# Create page links
				$pagesList = array ();
				for ($i = 1; $i <= $totalPages; $i++) {
					$pagesList[] = $this->createLink ($this->database, $this->table, NULL, NULL, NULL, ($i == $pageInUse ? 'selected' : NULL), $i, $linkArguments);
				}
				$pagesList[] = $this->createLink ($this->database, $this->table, NULL, NULL, 'All records', ($allRecords ? 'selected' : NULL), 'all');
				$viewPageHtml = "\n<p class=\"paginationlist\">View page: " . implode (' <span>|</span> ', $pagesList) . '</p>';
				
				# Construct the HTML
				$paginationHtml  = "\n<p class=\"paginationsummary\">Showing <strong>" . ($allRecords ? 'all records' : "page {$page} of {$totalPages}") . "</strong>" . ($allRecords ? '' : " (with max. {$limit} " . ($limit == 1 ? 'record' : 'records') . " per page)") . '.</p>';
				$paginationHtml .= $viewPageHtml;
			}
		}
		
		# Get the data
		if ($data) {
			$this->data = $data;
		} else {
			$query = 'SELECT ' . ($fullView ? '*' : $this->key) . " FROM `{$this->database}`.`{$this->table}` {$constraintsSql} ORDER BY {$orderBySql}{$paginationSql};";
			$this->data = $this->databaseConnection->getData ($query, "{$this->database}.{$this->table}");
		}
		$visibleRecords = count ($this->data);
		
		
		# Assign the count if pagination has not been set
		if (!$usePagination) {
			$totalRecords = count ($this->data);
		}
		
		# If there are no records, say so
		if (!$totalRecords) {
			$html .= "\n<p>There are no records in the <em>{$this->tableEntities}</em> table.</p>\n<p>You can " . $this->createLink ($this->database, $this->table, NULL, 'add', 'add a record', 'action button add') . '.</p>';
			return $html;
		}
		
		# Convert join data
		if ($this->settings['convertJoinsInView']) {
			$this->data = $this->convertJoinData ($this->data, $this->fields, false, $this->joins);
		}
		
		# Determine the visible fields
		$totalFieldsUnfiltered = count ($this->fields);
		$filterFieldsHtml = $this->filterFields ();
		
		# Convert booleans to ticks
		$this->data = application::booleansToTicks ($this->data, $this->fields);
		
		# Start a table, adding in metadata in full-view mode
		$table = array ();
		
		# Get the metadata names by taking the attributes of a known field, taking out unwanted fieldnames
		#!# Need to deal with clashing keys (the metadata fieldnames could be the same as real data); possible solution is not to allocate row keys but instead just use []
		if ($fullView && $this->settings['showMetadata'] /* && $this->userIsAdministrator */) {
			
			/*
			# Flag whether there are any comments
			$commentsFound = false;
			foreach ($this->fields as $fieldname => $attributes) {
				if ($attributes['Comment']) {$commentsFound = true;}
			}
			*/
			
			$metadataFields = array_keys ($this->fields[$this->key]);
			#!# Ideally this would be done case-insensitively in case of user default-setting errors
			$metadataFields = array_diff ($metadataFields, $this->settings['excludeMetadataFields']);
			#!# Ideally change 'Field' to 'Fieldname'
			$metadataFields = array_merge (array ('Field'), $metadataFields);
			foreach ($metadataFields as $metadataField) {
				
				# Skip join fields
				if (preg_match ('/^_/', $metadataField)) {continue;}
				
				$table[$metadataField] = array ($metadataField . ':', '', '', '',); // Placeholders against the starting Record/View/edit/clone/delete link headings
				if ($this->settings['showViewLink']) {$table[$metadataField][] = '';}
				foreach ($this->fields as $fieldname => $attributes) {
					
					# Add the metadata to the table; may be adjusted below
					$table[$metadataField][$fieldname] = $attributes[$metadataField];
					
					# Null fields
					if (strtolower ($metadataField) == 'null') {
						if (strtolower ($table[$metadataField][$fieldname]) == 'no') {
							$table[$metadataField][$fieldname] = 'Required';
						} elseif (strtolower ($table[$metadataField][$fieldname]) == 'yes') {
							$table[$metadataField][$fieldname] = '';
						}
					}
					
					# Add a key symbol for key fields
					if ((strtolower ($metadataField) == 'key') && (strtolower ($attributes[$metadataField]) == 'pri')) {
						$table[$metadataField][$fieldname] = '<strong>&#9554;</strong>&nbsp;Primary key';
					}
				}
			}
		}
		
		# Assemble the data, starting with the links
		foreach ($this->data as $key => $attributes) {
			$table[$key]['Record'] = '<strong>' . $this->createLink ($this->database, $this->table, $key, NULL, NULL, 'action view') . '</strong>';
			if ($this->settings['showViewLink']) {
				$table[$key]['View'] = $this->createLink ($this->database, $this->table, $key, NULL, 'View', 'action view');
			}
//			$actions = array ('edit', 'clone', 'delete');
			$actions = array ('edit', 'delete');
			foreach ($actions as $action) {
				$title = ucfirst ($action);
				$table[$key][$title] = $this->createLink ($this->database, $this->table, $key, $action, ucfirst ($action), "action {$action}");
			}
			
			# Add all the data in full view mode
			if ($fullView) {
				foreach ($attributes as $field => $value) {
					
					# Skip non-visible fields
					if (!isSet ($this->fields[$field])) {continue;}
					
					# Add the class to the underlying column key
					$fieldname = $field . ' ' . $this->fields[$field]['_type'];
					
					# Truncate if required
					if ($this->settings['truncateValues']) {
						$value = application::str_truncate ($value, $this->settings['truncateValues'], false, false, true, $htmlMode = false);
					}
					
					# Register cell
					$table[$key][$fieldname] = str_replace (array ("\r\n", "\n"), '<br />', htmlspecialchars ($value));
				}
			}
		}
		
		# Move delete to end if required
		if ($this->settings['moveDeleteToEnd']) {
			foreach ($table as $key => $record) {
				$delete = $record['Delete'];
				unset ($table[$key]['Delete']);
				$table[$key]['Delete'] = $delete;
			}
		}
		
		# Convert fieldnames containing joins
// application::dumpData ($this->fields);
/*
		if ($fullView && $this->userIsAdministrator) {
			foreach ($table['Field'] as $fieldname => $label) {
				if (isSet ($this->fields[$fieldname]['_field'])) {
					$table['Field'][$fieldname] = "<abbr title=\"{$label}\">{$this->fields[$fieldname]['_field']}</abbr>&nbsp;&raquo;<br />" . $this->createLink ($this->fields[$fieldname]['_targetDatabase'], $this->fields[$fieldname]['_targetTable'], NULL, NULL, NULL, NULL, NULL, NULL, true, $asHtmlNewWindow = true, $asHtmlTableIncludesDatabase = true);
				}
			}
		}
*/
		
		# Create the table headings and determine the link arguments
		$headings = array ();
		foreach ($this->fields as $field => $visible) {
			
			# Start a label string and an array of link arguments
			$label  = (($this->settings['commentsAsHeadings'] && $this->fields[$field]['Comment']) ? $this->fields[$field]['Comment'] : (isSet ($this->fields[$field]['_field']) ? $this->fields[$field]['_field'] : $field));
			$linkArguments = array ();
			
			# Add ordering to the link arguments if necessary
			if ($field != $orderBy) {
				$linkArguments['orderby'] = $field;
			}
			
			# Add direction to the link arguments if necessary
			if ($field == $orderBy) {
				if (!$descending) {
					$linkArguments['direction'] = 'desc';
				} else if ($apiOverrideInUseDescending) {
					$linkArguments['direction'] = 'asc';
				}
				$nonBreakingSpace = chr(0xc2).chr(0xa0);	// See http://www.tachyonsoft.com/uc0000.htm#U00A0
				$upArrow = chr(0xe2).chr(0x86).chr(0x91);	// See http://www.tachyonsoft.com/uc0021.htm#U2191
				$downArrow = chr(0xe2).chr(0x86).chr(0x93);	// See http://www.tachyonsoft.com/uc0021.htm#U2193
				$label .= $nonBreakingSpace . ($descending ? $upArrow : $downArrow);
			}
			
			# Add a class to the arguments if necessary
			$class = (($field == $orderBy) ? 'selected' : NULL);
			
			# Compile the link
			$fieldname = $field . ' ' . $this->fields[$field]['_type'];	// Add the class to the underlying column key
			$headings[$fieldname] = $this->createLink ($this->database, $this->table, NULL, NULL, $label, $class, $pageInUse, $linkArguments);
		}
		
		# Compile the HTML
		$totalFields = count ($this->fields);
		if (!$data) {	// Do not add text when data has been pre-supplied
			if (!$this->settings['hideTableIntroduction']) {
				$html .= "\n<p>This table, " . $this->createLink ($this->database) . ".{$this->tableEntities}, contains a total of <strong>" . ($totalRecords == 1 ? 'one record' : "{$totalRecords} records") . '</strong> (each with ' . ($totalFields == 1 ? 'one field' : "{$totalFields} fields") . ($totalFields != $totalFieldsUnfiltered ? ' shown' : '') . '), ' . ($allRecords ? 'with <strong>' . ($totalRecords == 1 ? 'this record' : 'all records') : 'of which <strong>' . ($visibleRecords == 1 ? 'one record is' : "{$visibleRecords} records are")) . ' listed below</strong>. You can switch to ' . ($fullView ? $this->createLink ($this->database, $this->table, NULL, 'listing', 'quick index', 'action button quicklist') . ' mode.' : $this->createLink ($this->database, $this->table, NULL, NULL, 'full-entry view', 'action button list') . ' (default) mode.') . '</p>';
			}
			if (!$this->settings['hideSearchBox']) {
				$html .= $this->searchBox (false);
			}
			if (!$this->settings['hideExport']) {
				$html .= "\n<p class=\"right\">" . $this->createLink ($this->database, $this->table, NULL, 'export', 'Export table', 'action button export') . '</p>';
			}
			$html .= "\n<p>You can " . $this->createLink ($this->database, $this->table, NULL, 'add', 'add a record', 'action button add') . '.</p>';
		}
		$html .= $paginationHtml;
		$html .= $filterFieldsHtml;
		#!# Enable sortability
		// $html .= "\n" . '<!-- Enable table sortability: --><script language="javascript" type="text/javascript" src="http://www.geog.cam.ac.uk/sitetech/sorttable.js"></script>';
		#!# Add line highlighting, perhaps using js
		#!# Consider option to compress output using str_replace ("\n\t\t", "", $html) for big tables
		$html .= application::htmlTable ($table, $headings, ($fullView ? 'sinenomine' : 'lines'), false, false, true, false, $addCellClasses = true, $addRowKeys = true, array (), $this->settings['compressWhiteSpace']);
		
		# Add the pagination links at the bottom of the page
		if ($usePagination) {
			if ($totalPages > 1) {
				$html .= $viewPageHtml;
			}
		}
		
		# Show the table
		return $html;
	}
	
	
	# Function to enable the user to filter visible fields
	private function filterFields ($unfilterableFields = array ('id'))
	{
		# If the field filtering UI is not enabled, just return an empty string
		if (!$this->settings['fieldFiltering']) {return false;}
		
		# Get the fields in use
		$checkboxes = array ();
		$i = 0;
		foreach ($this->fields as $field => $attributes) {
			if ($field == $this->key) {continue;}
			$checkboxes[$field] = $this->headings[$field];
		}
		
		# Determine whether to save state; note that this only supports a single table
		$stateSaving = false;
		if (is_string ($this->settings['fieldFiltering'])) {
			$separator = '.';
			if (substr_count ($this->settings['fieldFiltering'], $separator) == 4) {	// i.e. database.table.userfield.username.statefield
				$stateSaving = array ();
				list ($stateSaving['database'], $stateSaving['table'], $stateSaving['userfield'], $stateSaving['username'], $stateSaving['statefield']) = explode ($separator, $this->settings['fieldFiltering']);
			}
		}
		
		# Get the initial state if required
		$showFields = array_keys ($this->fields);
		if ($stateSaving) {
			if ($state = $this->databaseConnection->selectOne ($stateSaving['database'], $stateSaving['table'], array ($stateSaving['userfield'] => $stateSaving['username']), array ($stateSaving['statefield']))) {
				if ($state[$stateSaving['statefield']]) {	// If empty, show all fields
					$showFields = explode (',', $state[$stateSaving['statefield']]);
				}
			}
		}
		
		# Ensure there are no non-existent fields (which can cause the form not to display)
		$showFields = array_intersect ($showFields, array_keys ($checkboxes));
		
		# Start the HTML
		$html  = '';
		
		# Create the form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'databaseConnection' => $this->databaseConnection,
			'displayRestrictions' => false,
			'formCompleteText' => false,
			'reappear' => true,
			'display' => 'paragraphs',
			'requiredFieldIndicator' => false,
			'div' => 'limitfields',
			'name' => 'limitfields',
			'submitButtonText' => 'Filter!',
		));
		$form->checkboxes (array (
			'name' => 'fields',
			'title' => '<strong>Show fields</strong>',
			'required' => false,
			'values' => $checkboxes,
			'default' => $showFields,
			'columns' => 5,
			'truncate' => $this->settings['fieldFilteringCheckboxesTruncate'],
		));
		if ($result = $form->process ($html)) {
			$showFields = array ();
			foreach ($result['fields'] as $field => $checked) {
				if ($checked) {
					$showFields[] = $field;
				}
			}
		}
		
		# Obtain the checked checkboxes
		foreach ($checkboxes as $field => $label) {
			if (!in_array ($field, $showFields)) {
				unset ($this->fields[$field]);
			}
		}
		
		# Write the new fields into the state storage if required
		if ($stateSaving) {
			$newState = implode (',', array_keys ($this->fields));
			$this->databaseConnection->update ($stateSaving['database'], $stateSaving['table'], array ($stateSaving['statefield'] => $newState), array ($stateSaving['userfield'] => $stateSaving['username']));
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to urlencode a string and double-encode slashes
	private function doubleEncode ($string)
	{
		# Return the string, double-encoding slashes only
		return rawurlencode (str_replace ('/', '%2f', $string));
	}
	
	
	# Function to show all records in a table in full
	private function listing ()
	{
		# Return the HTML
		return $this->index ($fullView = false);
	}
	
	
	# Function to export all records in a table in full
	private function export ()
	{
		# Buffer and discard all current output so far
		ob_get_clean ();
		
		# Export the data
		$query = "SELECT * FROM {$this->database}.{$this->table};";
		$this->databaseConnection->serveCsv ($query, array (), $this->table);
		
		# End
		exit;
	}
	
	
	# Function to view a record
	private function record ($embed = false)
	{
		# Start the HTML
		$html  = '';
		
		# Show a flash if required
		if ($flashValue = application::getFlashMessage ('record', $this->baseUrl . '/')) {
			$message = "\n" . '<p><img src="/images/icons/tick.png" class="icon" alt="" /> <strong>The record has been ' . htmlspecialchars ($flashValue) . ', as below:</strong></p>';
			$html .= "\n<div class=\"graybox flashmessage\">" . $message . '</div>';
		}
		
		# Do lookups
		$data = $this->data;
		if ($this->settings['convertJoinsInView']) {
			$data = $this->convertJoinData ($this->data, $this->fields, false, $this->joins);
		}
		
		# Create the HTML
		if (!$embed) {
			$html .= "\n<p>The record <em>{$this->recordEntities}</em> in the table " . $this->createLink ($this->database, $this->table, NULL, NULL, NULL, 'action button list') . ' is as shown below.</p>';
			$html .= "\n<p>You can " . $this->createLink ($this->database, $this->table, $this->record, 'edit', 'edit', 'action button edit') . ', ' . $this->createLink ($this->database, $this->table, $this->record, 'clone', 'clone', 'action button clone') . ' or ' . $this->createLink ($this->database, $this->table, $this->record, 'delete', 'delete', 'action button delete') . ' this record.</p>';
		}
		$html .= application::htmlTableKeyed ($data, $this->headings);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to add a record
	private function add ()
	{
		# Wrapper to editing a record but with the key taken out
		return $this->recordManipulation (__FUNCTION__);
	}
	
	
	# Function to clone a record
	private function cloneRecord ()
	{
		# Wrapper to editing a record but with the key taken out
		return $this->recordManipulation ('clone');
	}
	
	
	# Function to edit a record
	private function edit ()
	{
		# Edit the record
		return $this->recordManipulation (__FUNCTION__);
	}
	
	
	# Function to do record manipulation
	private function recordManipulation ($action)
	{
		# Start the HTML
		$html = '';
		
		# Add the instruction
		if (!$this->settings['hideTableIntroduction']) {
			$html = "\n\n<p class=\"introduction\">On this page you can {$action} " . ($action == 'add' ? 'a record to ' : 'record ' . $this->createLink ($this->database, $this->table, $this->record, NULL, $this->record, 'action button view') . ' in ') . $this->createLink ($this->database, $this->table, NULL, NULL, NULL, 'action button list') . '.</p>';
		}
		
		#!# Lookup delete rights
		
		# Pre-fill the data
		$data = $this->data;
		
		# Set whether the key is editable
		$keyAttributes['editable'] = (($action != 'edit') && !$this->keyIsAutomatic);
		
		# Prevent addition of a new record whose key already exists
		if ($action == 'add') {
			if ($data) {
				$html .= "\n<p>You cannot add a record " . $this->createLink ($this->database, $this->table, $this->record, NULL, $this->record, 'action button view') . ' as it already exists. You can ' . $this->createLink ($this->database, $this->table, $this->record, 'clone', 'clone that record', 'action button clone') . ' or ' . $this->createLink ($this->database, $this->table, NULL, 'add', 'create a new record', 'action button add') . '.</p>';
				return $html;
			}
			$recordId = ($this->settings['record'] ? $this->settings['record'] : (isSet ($_GET['record']) ? $_GET['record'] : false));
			if ($recordId) {
				$data[$this->key] = $_GET['record'];
				$keyAttributes['editable'] = false;
			}
		}
		
		# Get the attributes
		$attributes = $this->attributes;
		
		# Create a list of uniqueable field names (i.e. those with PRI or UNI)
		$uniqueableFields = array ($this->key);
		foreach ($this->fields as $fieldname => $fieldAttributes) {
			if (strtolower ($fieldAttributes['Key']) == 'uni') {	// UNI for unique keys
				$uniqueableFields[] = $fieldname;
			}
		}
		
		# In editing mode (i.e. where the key value is known - NB adding is handled below), when requiring a forcedFileName, if %<uniquefield>, (e.g. '%id') has been set, replace with the value of that field in the submitted record
		if ($action == 'edit') {
			foreach ($attributes as $key => $widgetAttributes) {
				if (isSet ($widgetAttributes['forcedFileName'])) {
					if ($selectedUniqueableField = $this->uniqueableFieldSpecified ($widgetAttributes['forcedFileName'], $uniqueableFields)) {
						$attributes[$key]['forcedFileName'] = $data[$selectedUniqueableField];
					}
				}
			}
		}
		
		# Deal with automatic keys (which will now be non-editable)
		if (($action != 'edit') && ($action != 'clone') && $this->keyIsAutomatic) {
			#!# The first four values are a workaround for just placing the text '(automatically assigned)'
			$keyAttributes['type'] = 'select';
			$keyAttributes['forceAssociative'] = true;
			$keyAttributes['default'] = 1;
			$keyAttributes['discard'] = true;
			$keyAttributes['values'] = array (1 => '(Automatically assigned)');	// The value '1' is used to ensure it always validates, whatever the field specification is
		}
		
		# If adding or cloning, get current values for the key (and any other fields with uniqueness on them) to ensure that it cannot be re-entered
		if ($action == 'add' || $action == 'clone') {
			$keyAttributes['current'] = $this->getCurrentValues ($this->key);
		}
		foreach ($this->fields as $fieldname => $fieldAttributes) {
			if (strtolower ($fieldAttributes['Key']) == 'uni') {	// UNI for unique keys
				$excludeCurrent = ($action == 'edit' ? $data[$fieldname] : false);
				$attributes[$fieldname]['current'] = $this->getCurrentValues ($fieldname, $excludeCurrent);
			}
		}
		
		# If cloning, NULL out the key value if required
		if ($action == 'clone' && !$this->settings['clonePrefillsSourceKey']) {
			$data[$this->key] = NULL;
		}
		
		# Merge in the key handling, adding to anything explicitly supplied
		$attributes[$this->key] = $keyAttributes + (array_key_exists ($this->key, $attributes) ? $attributes[$this->key] : array ());
		
		# If not adding, unset any default values in the attributes
		if ($action != 'add') {
			foreach ($attributes as $field => $properties) {
				foreach ($properties as $key => $widgetAttributes) {
					if ($key == 'default') {
						unset ($attributes[$field][$key]);
						if (!$attributes[$field]) {			// Remove now-emptied attributes
							unset ($attributes[$field]);
						}
					}
				}
			}
		}
		
#!# Need to find the ultimate cause of the bug - may be because an auto ID isn't known at the point of insert
		# Fix up cases where image fields contain simply '1'
		if ($data) {
			foreach ($attributes as $fieldname => $widgetAttributes) {
				if (isSet ($widgetAttributes['forcedFileName'])) {		// I.e. if identified as a widget liable to this problem
					if (isSet ($data[$fieldname])) {
						if ($data[$fieldname] == '1') {
							$data[$fieldname] = $data[$this->key] . '.jpg';
						}
					}
				}
			}
		}
		
		# Load and create a form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'databaseConnection' => $this->databaseConnection,
			'displayRestrictions' => false,
			'formCompleteText' => false,
			'nullText' => $this->settings['nullText'],
			'cols' => $this->settings['cols'],
			'rows' => $this->settings['rows'],
			'picker' => $this->settings['datePicker'],
			'div' => $this->settings['formDiv'],
			'submitButtonPosition' => $this->settings['submitButtonPosition'],	# 'both' = Basically a workaround for when there is a refresh button (which then becomes the first 'submit' button, and thus the default action when pressing return)
			'unsavedDataProtection' => $this->settings['unsavedDataProtection'],
			'richtextEditorBasePath'			=> $this->settings['richtextEditorBasePath'],
			'richtextEditorToolbarSet'			=> $this->settings['richtextEditorToolbarSet'],
			'richtextEditorAreaCSS'				=> $this->settings['richtextEditorAreaCSS'],
			'richtextWidth'						=> $this->settings['richtextWidth'],
			'richtextHeight'					=> $this->settings['richtextHeight'],
			'richtextEditorFileBrowser'			=> $this->settings['richtextEditorFileBrowser'],
			'richtextEditorConfig.docType'		=> $this->settings['richtextEditorConfig.docType'],
			'mailAdminErrors' => true,
			'applicationName' => ($this->settings['application'] ? $this->settings['application'] : __CLASS__),
			'autofocus' => $this->settings['autofocus'],
		));
		$form->dataBinding (array (
			'database' => $this->database,
			'table' => $this->table,
			'data' => $data,
			'lookupFunction' => array ('database', 'lookup'),
			'simpleJoin' => $this->settings['simpleJoin'],
			'lookupFunctionParameters' => $this->settings['lookupFunctionParameters'],
			'lookupFunctionAppendTemplate' => "<a href=\"{$this->baseUrl}/" . ($this->includeDatabaseUrlPart ? '%database/' : '') . "%table/\" class=\"noarrow\" tabindex=\"998\" title=\"Click here to open a new window for editing these values; then click on refresh.\" target=\"_blank\"> ...</a>%refreshtabindex999",
			'includeOnly' => $this->includeOnly,
			'exclude' => $this->exclude,
			'attributes' => $attributes,
			'intelligence' => $this->settings['intelligence'],
			'int1ToCheckbox' => $this->settings['int1ToCheckbox'],
			'size' => $this->settings['size'],
		));
		
		# Add validation rules
		#!# Not yet working
		#!# This is pretty nasty API stuff; perhaps allow a direct passing as $sinenomine->form->validation instead
		if ($this->validation) {
			foreach ($validation as $validationRule) {
				$form->validation ($validationRule[0], $validationRule[1]);
			}
		}
		
		# Clear the data given that the form is not submitted
		$this->data = NULL;
		
		# Process the form
		if (!$record = $form->process ($html)) {
			return $html;
		}
		
		# Run a callback on the record data, if specified
		if (isSet ($this->settings['callback'][$this->database]) && isSet ($this->settings['callback'][$this->database][$this->table])) {
			$callback = $this->settings['callback'][$this->database][$this->table];
			$callbackClass = $callback[0];
			$callbackMethod = $callback[1];
			if (is_object ($callbackClass)) {
				$record = ${callbackClass}->${callbackMethod} ($record, $errorHtml);
			} else {
				$record = $callbackClass::$callbackMethod ($record, $errorHtml);
			}
			if ($errorHtml) {
				$html .= $errorHtml;
				return $html;
			}
		}
		
		#!# HACK to get uploading working by flattening the output; basically a special 'database' output format is needed at ultimateForm level
		if ((isSet ($record['filename']) && isSet ($record['filename'][0]))) {$record['filename'] = $record['filename'][0];}
		
		#!# End here if no filename
		
		# Insert/update the record
		$databaseAction = ($action == 'edit' ? 'update' : 'insert');
		$parameterFour = ($databaseAction == 'update' ? array ($this->key => $this->record) : NULL);
		if (!$result = $this->databaseConnection->$databaseAction ($this->database, $this->table, $record, $parameterFour)) {
			return $html .= $this->error ();
		}
		
		# Get the last insert ID of an insert
		if ($databaseAction == 'insert') {
			if ($this->keyIsAutomatic) {
				$this->record = $this->databaseConnection->getLatestId ();
			} else {
				$this->record = $record[$this->key];
			}
			$recordIncludingKey = $record;						// Take a copy of the record
			$recordIncludingKey[$this->key] = $this->record;	// Add in the key so that the key field is there, whether or not the key is automatic, for easy reference below
			$this->recordEntities = htmlspecialchars ($this->record);
			$this->recordLink = $this->createLink ($this->database, $this->table, $this->record, NULL, NULL, NULL, NULL, NULL, false);
			
			# For new records (i.e. not editing), when requiring a forcedFileName, if %id has been set, move the file (post-upload is the best we can do, since we don't know the ID until after the form has completed)
			$formFieldsSpecification = $form->getSpecification ();
			foreach ($attributes as $fieldname => $widgetAttributes) {
				if (!isSet ($formFieldsSpecification[$fieldname])) {continue;}	// E.g. The form doesn't show the ID when inserting so there is no field structure data
				if ($formFieldsSpecification[$fieldname]['type'] == 'upload') {
					if (isSet ($widgetAttributes['forcedFileName'])) {
						if ($selectedUniqueableField = $this->uniqueableFieldSpecified ($widgetAttributes['forcedFileName'], $uniqueableFields)) {
							$newlyUploadedFile = $widgetAttributes['directory'] . (is_array ($record[$fieldname][0]) ? $record[$fieldname][0] : $record[$fieldname]);
							$extension = pathinfo ($newlyUploadedFile, PATHINFO_EXTENSION);
							$newFilename = $recordIncludingKey[$selectedUniqueableField] . '.' . $extension;
							$moveTo = $widgetAttributes['directory'] . $newFilename;
							
							# Move the file
							#!# Error handling of some kind - but question is how to report this
							rename ($newlyUploadedFile, $moveTo);
							
							# Update the database to replace the key name from %id
							$filenameChange = array ($fieldname => $newFilename);
							$conditions = array ($this->key => $this->record, $fieldname => $record[$fieldname]);	// The current filename is also put into the conditions for safety
							if (!$filenameResult = $this->databaseConnection->update ($this->database, $this->table, $filenameChange, $conditions)) {
								return $html .= $this->error ();
							}
						}
					}
				}
			}
		}
		
		# Read back the data and make it available to the output
		$data = $this->databaseConnection->select ($this->database, $this->table, array ($this->key => $this->record));
		$this->data = $data[$this->record];
		
		# Confirm success and show the record
		$html .= "\n<p>The record " . $this->createLink ($this->database, $this->table, $this->record, NULL, $this->record, 'action button view') . " in the " . $this->createLink ($this->database, $this->table, NULL, NULL, $this->table, 'action button list') . " table has been " . ($action == 'edit' ? 'updated' : 'created') . ' successfully.</p>';
		$html .= "\n<p>You can now " . $this->createLink ($this->database, $this->table, $this->record, 'edit', 'edit it further', 'action button edit') . ', ' . $this->createLink ($this->database, $this->table, $this->record, 'clone', 'clone it', 'action button clone') . ', ' . $this->createLink ($this->database, $this->table, NULL, 'add', 'add another record', 'action button add') . ' or ' . $this->createLink ($this->database, $this->table, NULL, NULL, 'list all records', 'action button list') . '.</p>';
		$html .= "\n<p>The record" . ($action == 'edit' ? ' now' : '') . ' reads:</p>';
		$html .= "\n\n" . $this->record ($embed = true);
		#!# Replace this with the proper way of doing this
		if ($this->settings['refreshSeconds']) {
			$html .= "\n<meta http-equiv=\"refresh\" content=\"{$this->settings['refreshSeconds']};url={$this->tableLink}\">";
		}
		
		# Redirect if required, first setting a flash
		if ($this->settings['successfulRecordRedirect']) {
			$flashValue = ($action == 'edit' ? 'updated' : 'created');
			$recordPath = $this->createLink ($this->database, $this->table, $this->record, NULL, NULL, NULL, NULL, false, $asHtml = false);
			$html = application::setFlashMessage ('record', $flashValue, $recordPath, $html, $this->baseUrl . '/');
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to determine if a %<uniquefield> has been specified as an attribute, e.g. %id
	private function uniqueableFieldSpecified ($fieldReferenceString /* e.g. '%id' */, $uniqueableFields)
	{
		# If it doesn't start with a % then no match
		if (!preg_match ('/^%(.+)$/', $fieldReferenceString, $matches)) {return false;}
		
		# If the match is in the array, then return it
		$field = $matches[1];
		if (in_array ($field, $uniqueableFields)) {return $field;}
		
		# No match so return false
		return false;
	}
	
	
	# Search facility
	private function search ()
	{
		# Heading and form
		$html  = "<div class=\"graybox\">";
		$html .= $this->searchBox (false);
		$html .= "</div>\n<br />";
		
		# Create the results if a query is supplied
		if ($query = (isSet ($_GET[$this->settings['queryTerm']]) ? trim ($_GET[$this->settings['queryTerm']]) : '')) {
			$html .= $this->searchResults ($query);
		}
		
		# Show the HTML
		return $html;
	}
	
	
	# Function to create the search box
	private function searchBox ($minisearch = false)
	{
		$placeholderText = 'Search';
		$query = (isSet ($_GET[$this->settings['queryTerm']]) ? trim ($_GET[$this->settings['queryTerm']]) : '');
		$target = $this->createLink ($this->database, $this->table, $record = NULL, $action = 'search', NULL, NULL, NULL, false, $asHtml = false);
		return "\n\n" . '<form method="get" action="' . $target . '" class="' . ($minisearch ? 'minisearch' : 'search') . '" name="' . ($minisearch ? 'minisearch' : 'search') . '">
			<img src="/images/icons/magnifier.png" alt="" class="icon">
			<input name="' . $this->settings['queryTerm'] . '" type="text" size="' . ($minisearch ? '20' : '45') . '" value="' . $query . '" placeholder="' . $placeholderText . '" />&nbsp;<input value="Search!" accesskey="s" type="submit" class="button" />' /* . ($minisearch ? '' : " <span class=\"small\">&nbsp;[<a href=\"{$this->baseUrl}/search/\">Advanced search</a></span>]") */ . '
		</form>' . "\n";
	}
	
	
	# Function to provide search results
	#!# Convert all this to prepared statements
	private function searchResults ($searchPhrase)
	{
		# Escape the query for use in the SQL
		$searchPhraseEscaped = $this->databaseConnection->escape ($searchPhrase);
		
		# Construct field match extracts; see also ultimateForm.php:dataBinding() which contains some useful pointers
		$matchesSql = array ();
		foreach ($this->fields as $field => $attributes) {
			switch (true) {
				
				# VARCHAR text
				case (preg_match ('/^(char|varchar)/i', $attributes['Type'])):
					$matchesSql[$field] = "`{$field}` LIKE '%{$searchPhraseEscaped}%'";
					break;
					
				# Full-text
				/*
				case (preg_match ('/^(text|mediumtext|blob)/i', $attributes['Type'])):
					$matchesSql[$field] = "MATCH({$field}) AGAINST ('{$searchPhraseEscaped}')";
					break;
				*/
				case (preg_match ('/^(text|mediumtext)/i', $attributes['Type'])):
					$matchesSql[$field] = "`{$field}` LIKE '%{$searchPhraseEscaped}%'";
					break;
					
				# Enumerated list
				case (preg_match ('/^(enum)/i', $attributes['Type'])):
					$matchesSql[$field] = "`{$field}` = '{$searchPhraseEscaped}'";
					break;
					
				# Sets
				case (preg_match ('/^(set)/i', $attributes['Type'])):
					$matchesSql[$field] = "FIND_IN_SET('{$searchPhraseEscaped}',{$field}) > 0";
					break;
					
				# Floating-point numbers
				case (preg_match ('/^(float)/i', $attributes['Type']) && is_numeric ($searchPhrase)):
					$matchesSql[$field] = "`{$field}` = '{$searchPhraseEscaped}'";
					break;
					
				# Integer types, including years
				#!# Doesn't yet support negative integers; cannot use is_int as will never match what is an incoming string
				case (preg_match ('/^(int|tinyint|smallint|mediumint|bigint|year)/i', $attributes['Type']) && ctype_digit ($searchPhrase)):
					$matchesSql[$field] = "`{$field}` = '{$searchPhraseEscaped}'";
					break;
					
				# Dates
				case (preg_match ('/^(time|date|datetime|timestamp)/i', $attributes['Type'])):
					#!# Not yet implemented
					break;
					
				# Unknown field types have no implementation
				default:
			}
		}
		
		# Compile the matches SQL extracts
		$matchesSql = "\n(" . implode (")\n\t\t\tOR (", $matchesSql) . ')';
		
		# Add constraint, which overrides all others, if required
		if (isSet ($this->settings['constraint'][$this->database]) && isSet ($this->settings['constraint'][$this->database][$this->table])) {
			$matchesSql = "\n(" . $matchesSql . ') AND ' . $this->settings['constraint'][$this->database][$this->table];
		}
		
		# Construct a query
		#!# Currently doesn't support joins
		$query = "SELECT * FROM {$this->database}.{$this->table} WHERE {$matchesSql};";
		
		# Get the data
		if (!$data = $this->databaseConnection->getData ($query, "{$this->database}.{$this->table}")) {
			$html = "<p>\nSorry, no items were found.</p>";
			$html .= "\n<p>Do you want to " . $this->createLink ($this->database, $this->table, NULL, 'add', 'add a record', 'action button add') . '?</p>';
		} else {
			
			# Convert the results to a table
			$this->settings['pagination'] = false;
			$table = $this->index (true, $data);
			
			# Compile the HTML
			$records = count ($data);
			$html  = "\n<p>Matching text for '<strong>" . htmlspecialchars ($searchPhrase) . "</strong>' was found in <strong>" . ($records == 1 ? 'one entry' : "{$records} entries") . '</strong> in the ' . $this->createLink ($this->database, $this->table, NULL, NULL, NULL, 'action button list') . ' table:</p>';
			$html .= $table;
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to get current values (e.g. all the key numbers/names)
	private function getCurrentValues ($field, $exclude = false)
	{
		# Add a WHERE clause if excluding
		$where = array ();
		if ($exclude !== false) {
			$where[] = "{$field} != " . $this->databaseConnection->quote ($exclude);
		}
		$where[] = "{$field} IS NOT NULL";
		$where = ' WHERE (' . implode (' AND ', $where) . ')';
		
		# Get the current values (often the list of keys)
		$query = "SELECT {$field} FROM {$this->database}.{$this->table}{$where} ORDER BY {$field}";
		$values = $this->databaseConnection->getPairs ($query);
		
		# Return the values (often the list of keys)
		return $values;
	}
	
	
	# Function to return the current record
	public function getRecord ()
	{
		# Return the value
		return $this->record;
	}
	
	
	# Function to get table comments (descriptions)
	private function getTableComments ($database)
	{
		# Get the data
		$query = "SHOW TABLE STATUS FROM `{$this->database}`";
		$data = $this->databaseConnection->getData ($query);
		
		# Arrange as an associative array
		$comments = array ();
		foreach ($data as $table) {
			$comments[$table['Name']] = $table['Comment'];
		}
		
		# Return the comments
		return $comments;
	}
	
	
	# Function to deal with database error handling
	private function error ($userErrorMessage = 'A database error occured.')
	{
		# Get the error message
		$error = ($this->databaseConnection ? $this->databaseConnection->error () : 'No database connection available');
		
		# Get the name of the calling class
		$classReference = ($this->settings['application'] ? $this->settings['application'] . ' > ' : '') . __CLASS__;
		
		# Construct an e-mail message
		$message  = "A database error from {$classReference} occured.\nDebug details are:";
		$message .= "\n\n\nUser error message:\n{$userErrorMessage}";
		if ($this->databaseConnection) {
			if (is_array ($error) && isSet ($error[1])) {
				$message .= "\n\n" . ucfirst ($this->databaseConnection->vendor) . " error number:\n{$error[1]}";
			}
			if (is_array ($error) && isSet ($error[2])) {
				$message .= "\n\nError text:\n{$error[2]}";
			}
			if (is_array ($error) && isSet ($error['query']) && $error['query']) {$message .= "\n\nQuery:\n" . $error['query'];}
		}
		$message .= "\n\nURL:\n" . $_SERVER['_PAGE_URL'];
		if ($_POST) {$message .= "\n\nData in \$_POST:\n" . print_r ($_POST, 1);}
		
		# Construct the on-screen error message
		$html  = "\n<p class=\"warning\">{$userErrorMessage}</p>";
		$html .= "\n<p>" . ($this->settings['administratorEmail'] ? 'This problem has been reported to' : 'Please report this problem to') . ' the Webmaster.</p>';
		if ($this->settings['displayErrorDebugging']) {$html .= "\n\n<p>Debugging details:</p><div class=\"graybox\">\n<pre>" . wordwrap (htmlspecialchars ($message)) . '</pre></div>';}
		
		# Report to the webmaster by e-mail if required
		if ($this->settings['administratorEmail']) {
			application::sendAdministrativeAlert ($this->settings['administratorEmail'], __CLASS__, "Database error in {$classReference}", $message);
		}
		
		# Return the HTML
		return $html;
	}
	
	
	/*
	# Function to parse dataBinding overrides
	function parseOverrides ($setting)
	{
		# End if false or not an array
		if (!$setting || !is_array ($setting)) {return false;}
		
		# Return the value (usually an array) if it's in the hierarchy
		#!# Add wildcard support, i.e. '*' to represent any database or any table
		if (isSet ($setting[$this->database]) && is_array ($setting[$this->database]) && isSet ($setting[$this->database][$this->table]) && is_array ($setting[$this->database][$this->table])) {
			return $setting[$this->database][$this->table];
		}
		
		# Otherwise return false
		return false;
	}
	*/
	
	
	# Function to do referential integrity checks
	private function joinsTo ($database, $table, $record = false, $returnAsString = true)
	{
		# Start an array of joins
		$joins = array ();
		
		# Start a cache of fields
		$fieldsCache = array ();
		
		# Loop through each database table's fields
		foreach ($this->databases as $database) {
			$tables = $this->databaseConnection->getTables ($database);
			foreach ($tables as $table) {
				$fields = $this->databaseConnection->getFields ($database, $table);
				$simpleJoin = ($this->settings['simpleJoin'] ? array ($database, $table, $tables) : false);
				foreach ($fields as $field => $attributes) {
					
					# If a join is found, add it to the list
					if ($join = database::convertJoin ($field, $simpleJoin)) {
						if (($this->database == $join['database']) && ($this->table == $join['table'])) {
							$joins[$database][$table][$field] = true;
							
							# Cache the fields
							$fieldsCache[$database][$table] = $fields;
						}
					}
				}
			}
		}
		
		# Get any records being joined
		$joinedRecords = array ();
		if ($joins) {
			foreach ($joins as $database => $tables) {
				foreach ($tables as $table => $fields) {
					foreach ($fields as $field => $boolean) {
						$uniqueField = $this->databaseConnection->getUniqueField ($database, $table, $fieldsCache[$database][$table]);	// Fields cache is used to avoid the fields being looked up again
						if ($result = $this->databaseConnection->select ($database, $table, $conditions = array ($field => $record), $columns = array ($uniqueField), $associative = true, $orderBy = $uniqueField)) {
							$joinedRecords[$database][$table] = array_keys ($result);
						}
					}
				}
			}
		}
		
		# Return the joins as an array if required
		if (!$returnAsString) {return $joinedRecords;}
		
		# Return an empty string if no records
		if (!$joinedRecords) {return '';}
		
		# Loop through each database and table part of the hierarchical list of joins
		foreach ($joinedRecords as $database => $tables) {
			foreach ($tables as $table => $joins) {
				
				# Loop through each record part of the hierarchical list of joins
				$links = array ();
				foreach ($joins as $joinedRecord) {
					$links[] = $this->createLink ($database, $table, $joinedRecord, NULL, NULL, $class = 'action button view', NULL, NULL, true, $asHtmlNewWindow = true);
				}
				
				# Compile the HTML
				$tableLinks[] = 'In ' . $this->createLink ($database, $table, NULL, NULL, NULL, NULL, NULL, NULL, true, $asHtmlNewWindow = true, $asHtmlTableIncludesDatabase = true) . ': ' . ((count ($joins) == 1) ? 'record' : 'records') . ' ' . implode (', ', $links);
			}
		}
		
		# Compile the HTML
		$html = "\n<p>You can't currently delete the record <em>" . $this->createLink ($this->database, $this->table, $record, NULL, NULL, $class = 'action button view') . "</em>, because the following join to it:</p>" . application::htmlUl ($tableLinks);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function dealing with consistent link creation, taking account of whether URL-rewriting is on
	private function createLink ($database = NULL, $table = NULL, $record = NULL, $action = NULL, $labelSupplied = NULL, $class = NULL, $page = NULL, $arguments = false, $asHtml = true, $asHtmlNewWindow = false, $asHtmlTableIncludesDatabase = false, $tooltips = true)
	{
		# Start with the base URL and the database URL if required
		if ($this->settings['rewrite']) {
			$link = $this->baseUrl . '/' . (($database && $this->includeDatabaseUrlPart) ? $this->doubleEncode ($database) . '/' : '');
		} else {
			$link = $this->baseUrl . '/' . (($database && $this->includeDatabaseUrlPart) ? '?database=' . $this->doubleEncode ($database) : '?');
		}
		
		# Define the text
		$label = $database;
		$tooltip = 'Database';
		
		# Add the table if required
		if ($table !== NULL) {
			$tableMoniker = $table;
			if ($this->settings['tableUrlMoniker']) {$tableMoniker = $this->settings['tableUrlMoniker'];}
			$link .= ($this->settings['rewrite'] ? $this->doubleEncode ($tableMoniker) . '/' : (($database && $this->includeDatabaseUrlPart) ? ($asHtml ? '&amp;' : '&') : '') . 'table=' . $this->doubleEncode ($tableMoniker));
			$label = ($asHtmlTableIncludesDatabase ? "{$database}.{$table}" : $table);
			$tooltip = ($asHtmlTableIncludesDatabase ? "Database &amp; table" : 'Table');
		}
		
		# Add a page number if required
		if ($page !== NULL) {
			if ($page != 1) {	// Don't add pointless 'page1.html', under the maxim of keeping URLs short as possible
				if ($page == 'all') {
					$link .= ($this->settings['rewrite'] ? "all.html" : ($asHtml ? '&amp;' : '&') . "page=all");
				} else {
					$link .= ($this->settings['rewrite'] ? "page{$page}.html" : ($asHtml ? '&amp;' : '&') . "page={$page}");
				}
			}
			$label = $page;
			$tooltip = 'Page';
		}
		
		# Add the record if required
		if ($record !== NULL) {
			$link .= ($this->settings['rewrite'] ? $this->doubleEncode ($record) . '/' : ($asHtml ? '&amp;' : '&') . 'record=' . $this->doubleEncode ($record));
			$label = $record;
			$tooltip = 'Record';
		}
		
		# If no database is supplied, use the hostname
		if ($database === NULL) {
			$link = $this->baseUrl . '/';
			$label = $this->hostnameEntities;
			$tooltip = 'Hostname';
		}
		
		# Add the action if required
		if ($action) {
			$link .= ($this->settings['rewrite'] ? htmlspecialchars ($action) . '.html' : ($asHtml ? '&amp;' : '&') . 'do=' . $this->doubleEncode ($action));
			$tooltip = ucfirst ($action);
		}
		
		# Add the action if required
		if ($arguments && is_array ($arguments)) {
			foreach ($arguments as $key => $value) {
				$argumentsString[] = htmlspecialchars ("{$key}={$value}");
			}
			$argumentsString = implode (($asHtml ? '&amp;' : '&'), $argumentsString);
			$link .= ($this->settings['rewrite'] ? '?' : ($asHtml ? '&amp;' : '&')) . $argumentsString;
		}
		
		# Compile as HTML if necessary
		if ($asHtml) {
			$label = ($labelSupplied === NULL ? $label : $labelSupplied);
			$labelEntities = htmlspecialchars ($label);
			if ($action == 'delete') {$labelEntities .= '&hellip;';}
			$title = array ();
			if ($tooltips) {$title[] = $tooltip;}
			if ($asHtmlNewWindow) {$title[] = '(Opens in a new window)';}
			$link = "<a href=\"{$link}\"" . ($asHtmlNewWindow ? " target=\"_blank\"" : '') . ($title ? ' title="' . implode (' ', $title) . '"' : '') . ($class ? " class=\"{$class}\"" : '') . ">{$labelEntities}</a>";
		}
		
		# Return the link
		return $link;
	}
	
	
	# Function to delete a record
	private function delete ()
	{
		# Start the HTML
		$html = '';
		
		#!# Lookup delete rights
		
		# Do referential integrity checks; end if joins exist
		if ($joins = $this->joinsTo ($this->database, $this->table, $this->record)) {
			$html .= $joins;
			return $html;
		}
		
		# Load and create a form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'databaseConnection' => $this->databaseConnection,
			'formCompleteText' => false,
			'nullText' => $this->settings['nullText'],
			'div' => 'graybox',
		));
		
		# Form text/widgets
		$form->heading ('p', 'Do you really want to delete record ' . $this->createLink ($this->database, $this->table, $this->record, NULL, $this->record, 'action button view') . ', whose data is shown below?');
		$form->select (array (
			'name'				=> 'confirmation',
			'title'				=> 'Confirm deletion',
			'required'			=> 1,
			'forceAssociative'	=> true,
			'values'			=> array ('No, do NOT delete the record', 'Yes, delete this record permanently'),
		));
		
		# Show the record
		$form->heading ('p', $this->record ($embed = true));
		
		# Clear the data given that the form is not submitted
		$this->data = NULL;
		
		# Process the form
		if (!$result = $form->process ($html)) {
			return $html;
		}
		
		# Confirm that the record has not been deleted
		if (!$result['confirmation']) {
			$html .= "\n<p>The record " . $this->createLink ($this->database, $this->table, $this->record, NULL, $this->record, 'action button view') . ' has <strong>not</strong> been deleted.</p>';
			$html .= "\n<p>You may wish to " . $this->createLink ($this->database, $this->table, NULL, NULL, 'return to the list of records', 'action button list') . '.</p>';
			return $html;
		}
		
		# Delete the record and confirm success
		if (!$this->databaseConnection->delete ($this->database, $this->table, array ($this->key => $this->record))) {
			return $html .= $this->error ();
		}
		$html .= "<p>The record <em>{$this->recordEntities}</em> in the table " . $this->createLink ($this->database, $this->table, NULL, NULL, NULL, 'action button list') . ' has been deleted.</p>';
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to check for integer fields nearing overflow
	private function overflow ($hideReservedTables = true, $checkPercentage = 75, $mail = false)
	{
		# List supported sizes, from http://dev.mysql.com/doc/refman/5.1/en/numeric-types.html
		$ranges = array (
			'tinyint\([0-9]+\)' => array (-128, 127),
			'tinyint\([0-9]+\) unsigned' => array (0, 255),
			'smallint\([0-9]+\)' => array (-32768, 32767),
			'smallint\([0-9]+\) unsigned' => array (0, 65535),
			'mediumint\([0-9]+\)' => array (-8388608, 8388607),
			'mediumint\([0-9]+\) unsigned' => array (0, 16777215),
			'int\([0-9]+\)' => array (-2147483648, 2147483647),
			'int\([0-9]+\) unsigned' => array (0, 4294967295),
			'bigint\([0-9]+\)' => array (-9223372036854775808, 9223372036854775807),
			'bigint\([0-9]+\) unsigned' => array (0, 18446744073709551615),
		);
		
		# Import from the settings a list of reserved databases for checking against
		$databases = $this->databases;
		
		# Start a table of problematic fields
		$problems = array ();
		
		# Get the list of databases and loop through them
		foreach ($databases as $database) {
			
			# Create a list of tables and loop through them
			$tables = $this->databaseConnection->getTables ($database);
			foreach ($tables as $table) {
				
				# Create a list of tables and loop through them
				$fields = $this->databaseConnection->getFields ($database, $table);
				foreach ($fields as $fieldname => $field) {
					
					# Check for integer fields
					if (substr_count (strtolower ($field['Type']), 'int(')) {
						
						# Match the field specification
						foreach ($ranges as $specification => $range) {
							if (preg_match ("/^{$specification}$/", strtolower ($field['Type']), $matches)) {
								
								# Get the highest (max) record for this
								// $query = "SELECT `{$fieldname}` as highest FROM `{$database}`.`{$table}` ORDER BY `{$fieldname}` DESC LIMIT 1;";
								$query = "SELECT MAX(`{$fieldname}`) as highest FROM `{$database}`.`{$table}`;";
								$data = $this->databaseConnection->getOne ($query);
								$highest = $data['highest'];
								
								# Compare
								$slotsAvailable = $range[1] - $range[0];
								if (($highest / $slotsAvailable) > ($checkPercentage / 100)) {
									$problems[] = array (
										'Database' => $database,
										'Table' => $table,
										'Field' => $fieldname,
										'Specification' => $field['Type'],
										'Range' => number_format ($range[0]) . ' to ' . number_format ($range[1]),
										'Highest record' => number_format ($highest),
										'Position in range' => round (($highest / $slotsAvailable) * 100) . '%',
									);
								}
								
								# Don't check for more ranges for this field
								break;
							}
						}
					}
				}
			}
		}
		
		# Create the table
		if ($problems) {
			$html  = "\n<p>This list shows integer fields which are in danger of overflow (above {$checkPercentage}% of available slots).</p>";
			$html .= application::htmlTable ($problems, array (), 'lines' . ($mail ? '" border="1' : ''), $keyAsFirstColumn = false);
		} else {
			$html  = "\n<p class=\"success\">No fields have been found which are in danger of overflow (above {$checkPercentage}% of available slots).</p>";
		}
		
		# Mail the HTML table to a given e-mail address if required
		if ($mail) {
			if ($problems) {
				application::utf8Mail ($mail, $this->actions[__FUNCTION__]['description'], $html, "From: {$mail}", NULL, 'text/html');
			}
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to show a complete hierarchical listing of the database structure
	#!# Privileges?
	#!# Database limitation mode
	private function structure ($hideReservedTables = true)
	{
		# Add notes at the start
		$html  = "\n<p>This shows the database/table/field hierarchy. Fields with a <strong>key</strong> are shown in bold" . ($this->settings['highlightMainTable'] ? ', as are tables whose name matches the database they are contained in' : '') . '.</p>';
		
		# Import from the settings a list of reserved databases for checking against
		$databases = $this->databases;
		
		# Get the list of databases and loop through them
		$listDatabaseItems = array ();
		foreach ($databases as $database) {
			
			# Create a list of tables and loop through them
			$tables = $this->databaseConnection->getTables ($database);
			$listTableItems = array ();
			foreach ($tables as $table) {
				
				# Create a list of fields and loop through them
				$simpleJoin = ($this->settings['simpleJoin'] ? array ($database, $table, $tables) : false);
				$fields = $this->databaseConnection->getFields ($database, $table);
				$listFieldItems = array ();
				foreach ($fields as $field) {
					
					# Construct HTML for the joins if one exists
					$join = database::convertJoin ($field['Field'], $simpleJoin);
					$joinedToHtml = ($join ? ('&nbsp;&nbsp;(joined to <a href="#' . htmlspecialchars ("{$join['database']}.{$join['table']}") . '">' . htmlspecialchars ("{$join['database']}.{$join['table']}") . '</a>)') : '');
					
					# Construct HTML showing the collation
					$collationHtml = '';
					if ($field['Collation']) {
						$collationHtml = ' [' . $field['Collation'] . ']';
						if ($field['Collation'] != 'utf8mb4_unicode_ci') {
							$collationHtml = "<span class=\"warning\">{$collationHtml}</span>";
						}
					}
					
					# Add the field type
					$typeHtml = ' <span style="color: ' . ($field['Type'] == 'blob' ? 'red' : 'gray') . "\">{$field['Type']}</span>";
					
					# Add the comment
					$commentHtml = ($field['Comment'] ? " <em>'" . htmlspecialchars ($field['Comment']) . "'</em>" : '');
					
					# Add the field name to the list
					$fieldLink = $this->createLink ($database, $table, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, false) . ($field['Key'] ? '' : ($this->settings['rewrite'] ? '?' : '&amp;') . 'orderby=' . htmlspecialchars ($field['Field']));
					$listFieldItems[] = "<a href=\"{$fieldLink}\" title=\"Field\">" . ($field['Key'] ? '<strong>' : '') . htmlspecialchars ($field['Field']) . ($field['Key'] ? '</strong>' : '') . '</a>' . $joinedToHtml . $typeHtml . $collationHtml . $commentHtml;
				}
				
				# Add the table and its fields
				$listTableItems[] = '<a name="' . htmlspecialchars ("{$database}.{$table}") . '">' . ($this->settings['highlightMainTable'] && ($database == $table) ? '<strong>' : '') . $this->createLink ($database, $table, NULL, NULL, NULL, (($table == 'tallis') ? 'tallis' : '')) . ($this->settings['highlightMainTable'] && ($database == $table) ? '</strong>' : '') . application::htmlUl ($listFieldItems, 4, 'normal');
			}
			
			# Do not show known software in detail
			$knownStructures = array (
				'^wp_'					=> 'Wordpress',
				'^oc_address$'			=> 'OpenCart',
				'^oc_account_terms$'	=> 'ownCloud',
				'^acknowledges$'		=> 'Zabbix',
			);
			foreach ($knownStructures as $regexp => $softwareName) {
				if (preg_match ("/{$regexp}/", $tables[0])) {
					$listTableItems = array ("[{$softwareName} database structure]");
					break;
				}
			}
			
			# Compile the tables display
			$tablesListing = application::htmlUl ($listTableItems, 3, 'spaced');
			
			# Add the database and its tables
			$listDatabaseItems[] = '<a name="' . htmlspecialchars ($database) . '">' . $this->createLink ($database) . $tablesListing;
		}
		
		# Add the server and its databases
		$listServerItems[] = $this->createLink () . application::htmlUl ($listDatabaseItems, 2);
		$html .= application::htmlUl ($listServerItems, 1);
		
		# Return the result
		return $html;
	}
	
	
	# Function (which can be run statically) to convert key numbers/names in a set of records
	private function convertJoinData ($data, $fields, $databaseConnection = false, $joins = false, $showNumberFields = false, $unsetNumericKey = true, $intJoinZeroClear = true, $modifyDisplay = true, $modifyDisplayConvertUrls = true)
	{
		# Load required libraries (only really required if running statically)
		require_once ('application.php');
		require_once ('database.php');
		
		# Return the data unmodified if there is none or it is not an array
		if (!$data || (!is_array ($data))) {return $data;}
		
		# Get the database connection if running this statically
		if ($databaseConnection) {
			$this->databaseConnection = $databaseConnection;
		}
		
		# Use the pre-computed joins, or compute them if there are none
		if ($joins === false) {
			foreach ($fields as $fieldname => $fieldAttributes) {
				$simpleJoin = ($this->settings['simpleJoin'] ? array ($this->database, $this->table, $this->tables) : false);
				if ($matches = database::convertJoin ($fieldAttributes['Field'], $simpleJoin)) {
					$joins[$fieldname] = $matches;
				}
			}
		}
		
		# Return the data unmodified if there are no joins
		if (!$joins) {return $data;}
		
		# Determine if the data is multidimensional, i.e. a set of records in an array or just a single record
		$isMultidimensional = application::isMultidimensionalArray ($data);
		
		# If the record is not multi-dimensional, wrap it first
		if (!$isMultidimensional) {
			$records[0] = $data;
		} else {
			$records = $data;
		}
		
		# Start an array to hold values that need to be looked up
		$unconverted = array ();
		
		# Go through each record to get the unique values in the joined fields and create a list of them
		foreach ($records as $key => $record) {
			foreach ($record as $field => $value) {
				if (array_key_exists ($field, $joins)) {
					if ($value != '') {	// Do not include empty strings, otherwise the performance will decline massively
						$unconverted[$field][$value] = $value;
					}
				}
			}
		}
		
		# Perform the conversions for each unconverted value
		$conversions = array ();
		foreach ($unconverted as $field => $values) {
			
			# Get the unique field name for the target table, or skip if fails
			$uniqueField = $this->databaseConnection->getUniqueField ($joins[$field]['database'], $joins[$field]['table']);
			
			# Quote the values for use in a regexp
			#!# preg_quote doesn't necessary match MySQL REGEXP - need to check this
			foreach ($values as $key => $value) {
				$values[$key] = preg_quote ($value);
			}
			
			# Get the data
			$query = "SELECT * FROM `{$joins[$field]['database']}`.`{$joins[$field]['table']}` WHERE `{$uniqueField}` REGEXP '(^" . implode ('|', $values) . "$)';";
			$tempData = $this->databaseConnection->getData ($query, "{$joins[$field]['database']}.{$joins[$field]['table']}");
			
			# Loop through each data set and convert each record
			foreach ($tempData as $key => $record) {
				
				# If required, unset a numeric key, i.e. don't show key in the join data
				if ($unsetNumericKey) {
					if (is_numeric ($record[$uniqueField])) {
						unset ($record[$uniqueField]);
					}
				}
				
				# Compile the data, showing either all fields in the joined data, or the first $showNumberFields fields
				if ($showNumberFields) {
					$items = array ();
					$i = 0;
					foreach ($record as $recordKey => $item) {
						if ($i == $showNumberFields) {break;}
						$items[$recordKey] = $item;
						$i++;
					}
					$record = $items;
				}
				
				# If required, modify the display, before being passed to the implode stage
				if ($modifyDisplay) {
					#!# This will be rather inefficient
					$fields = $this->databaseConnection->getFields ($joins[$field]['database'], $joins[$field]['table']);
					$record = $this->modifyDisplay ($record, $fields, $modifyDisplayConvertUrls);
				}
				
				# Put the new value into the data
				#!# Ideally surround this with <span class="comment"> but requires difficult changes to the htmlspecialchars handling
				$conversions[$field][$key] = implode (utf8_encode (' » '), $record);
			}
		}
		
		# Substitute in the conversions if they have been found
		foreach ($records as $key => $record) {
			foreach ($record as $field => $value) {
				if (array_key_exists ($field, $joins)) {
					$records[$key][$field] = (isSet ($conversions[$field][$value]) ? $conversions[$field][$value] : (($intJoinZeroClear && ($value == '0')) ? NULL : $value));
				}
			}
		}
		
		# If the record is not multi-dimensional, unwrap it
		$data = ($isMultidimensional ? $records : $records[0]);
		
		# Return the data
		return $data;
	}
	
	
	# Function to modify display of a record
	private function modifyDisplay ($record, $fields, $convertUrls = true)
	{
		# Loop through each field
		foreach ($record as $fieldname => $value) {
			
			# Convert timestamp fields to human-readable text
			if (($fields[$fieldname]['Type'] == 'timestamp') || ($fields[$fieldname]['Type'] == 'datetime')) {
				if ($value == '0000-00-00 00:00:00') {
					$value = NULL;
				} else {
					require_once ('timedate.php');
					$value = timedate::convertTimestamp ($value);
				}
			}
			
			# Convert NULL dates
			if ($fields[$fieldname]['Type'] == 'date') {
				if ($value == '0000-00-00') {
					$value = NULL;
				} else {
					#!# Convert date needed here as above
				}
			}
			
			# Make entity-safe
//			# Make entity-safe if not a richtext field
//			if ($fields[$fieldname]['Type'] != 'text') {
				$value = htmlspecialchars ($value);
//			}
			
			# Replace line breaks
			#!# This should be disabled for richtext
			$value = str_replace ("\n", "<br />\n", $value);
			
			# Convert URLs if required
			if ($convertUrls) {
				require_once ('application.php');
				$value = application::makeClickableLinks ($value, false, '[Link]');
			}
			
			# Put the new value into the data
			$record[$fieldname] = $value;
		}
		
		# Return the cleaned record
		return $record;
	}
	
	
	# Function to create a footer
	private function pageHeader ()
	{
		# End if not in GUI mode
		if (!$this->settings['gui']) {return false;}
		
		# Otherwise use the default header
		$html = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<title>sineNomine database administrator%position</title>
	%refreshHtml
		<style type="text/css" media="screen">
			/* Layout */
			body {text-align: center;}
			#container {margin-left: 10px; margin-right: 10px; text-align: left;}
			#header {height: 5em; border-bottom: 1px solid #ddd; margin-bottom: 25px;}
			#menu {float: left; width: 16em; overflow: auto; padding: 1px; margin-top: 0; margin-bottom: 1.4em;}
			#content {margin-left: 18em;}
			#footer {clear: both; border-top: 1px solid #ddd; margin-top: 30px;}
			/* Body */
			body, input, textarea, select, option, checkbox {font-family: verdana, arial, helvetica, sans-serif;}
			/* Menu */
			#menu ul {list-style: none; margin-left: 0; padding-left: 0;}
			#menu ul li {margin-bottom: 2px; white-space: nowrap; padding: 0;}
			#menu ul li a {margin: 0; color: gray; display: block; padding: 1px 2px 3px; /* border: 1px solid #eee;*/}
			* html #menu ul li a {width: 100%;} /* IE6 fix */
			#menu a:hover, #menu a.current:hover {background-color: #eee; text-decoration: none;}
			#menu ul li a.current {color: #222; font-weight: bold; background-color: #eee; border-bottom: 1px solid #ccc;}
			#menu ul li ul {margin-left: 15px; margin-bottom: 10px;}
			#menu ul li ul li a {padding: 1px;}
			#menu ul li ul li a.current {color: #222; font-weight: bold; background-color: #f7f7f7; border-bottom: 0;}
			#menu ul li span.count {color: #999; font-weight: normal;}
			/* Fonts - sizing uses technique at http://www.thenoodleincident.com/tutorials/typography/ */
			body {font-size: 68%;}
			input, textarea, select, option, checkbox {font-size: 1em;}
			h1, h2, h3, h4, h5, h6 {color: #603; padding-bottom: 0; margin-bottom: 1em;}
			h1 {font-size: 1.5em;}
			/* Header */
			#header h1, #header h1 a, #header h1 a:visited {font-size: 1.4em; font-weight: normal; color: #bbb; text-decoration: none; margin-bottom: 0;}
			p#logout {position: absolute; right: 0; top: 0; margin-right: 20px;}
			p#phpmyadmin {position: absolute; right: 0; top: 2em; margin-right: 20px;}
			/* Breadcrumb trail */
			p.locationline {margin-top: 5px; margin-bottom: 2.4em;}
	' . $this->getContentCss () . '
		</style>
	<script language="javascript" type="text/javascript"><!--
		function setFocus() {
			if (document.forms.length > 0) {
				var field = document.forms[0];
				for (i = 0; i < field.length; i++) {
					if ((field.elements[i].type == "text") || (field.elements[i].type == "textarea") || (field.elements[i].type.toString().charAt(0) == "s")) {
						document.forms[0].elements[i].focus();
						break;
					}
				}
			}
		}
	--></script>
</head>
<body onload="setFocus()">

<div id="container">

	<div id="header">
		<h1><a href="%baseUrl">sineNomine data editor</a></h1>
		%logoutLink
		%phpMyAdminLink
		%breadcrumbTrail
	</div>
	
';
		# Use a specified header if required
		if ($this->settings['headerHtml']) {$html = $this->settings['headerHtml'];}
		
		# Substitute the breadcrumb trail
		$substitutions = array (
			'%baseUrl' => $this->baseUrl . '/',
			'%refreshHtml' => ($this->user ? '<meta http-equiv="REFRESH" content="' . ($this->settings['autoLogoutTime'] + 5) . "; URL=" . htmlspecialchars ($_SERVER['REQUEST_URI']) . '" />' : ''),
			'%logoutLink' => ($this->user ? '<p id="logout"><a href="' . $this->logoutUrl . '">[Logout]</a></p>' : ''),
			'%phpMyAdminLink' => ($this->phpMyAdminUrl ? '<p id="phpmyadmin"><a href="' . $this->phpMyAdminUrl . '" target="_blank">[phpMyAdmin]</a></p>' : ''),
			'%autoLogoutTime' => $this->settings['autoLogoutTime'] + 5,
			'%breadcrumbTrail' => $this->breadcrumbTrail (),
			'%position' => $this->position ($this->settings['hostnameInTitle']),
		);
		$html = strtr ($html, $substitutions);
		
		# Return the header
		return $html;
	}
	
	
	# Function to get the styles
	public function getContentCss ($addSurroundingTags = false)
	{
		# Define the HTML
		$html  = '
			/* Links */
			a {text-decoration: none;}
			a:hover {text-decoration: underline;}
			/* Lists */
			ul li {margin-top: 3px;}
			ul.spaced li, li.spaced {margin-top: 12px;}
			ul.normal li, li.normal {margin-top: 3px;}
			/* Forms */
			fieldset {border: 0; padding: 0;}
			td {padding: 10px 2px 0;}
			.error, .warning {color: red;}
			.comment {color: gray;}
			input, select, textarea, option, td.data label {color: #603;}
			.maintable {font-weight: bold;}
			.tallis {font-size: 16px; font-weight: bold; color: blue; filter: shadow(color=gold, strength=10); height: 30px;}
			/* Summary table */
			table.sinenomine {background-color: #fff; border: 1px solid #dcdcdc;}
			table.sinenomine th, table.sinenomine td {padding: 4px 4px;}
			table.sinenomine th, table.sinenomine th a {vertical-align: top; background-color: #7397dd; color: #fff; text-align: left;}
			table.sinenomine th a {display: block;}
			table.sinenomine th a.selected {background-color: #36c}
			table.sinenomine th span.orderby {background-color: brown;}
			table.sinenomine td {background-color: #ebeff4; border-bottom: 1px solid #dcdcdc;}
			table.sinenomine tr:hover td {background-color: #eaeafa;}
			table.sinenomine tr.Field td, table.sinenomine tr.Type td, table.sinenomine tr.Null td, table.sinenomine tr.Key td, table.sinenomine tr.Extra td, table.sinenomine tr.Privileges td, table.sinenomine tr.Comment td, table.sinenomine tr.Collation td, table.sinenomine tr.Default td {padding: 1px 4px; color: #777; font-size: 0.93em; vertical-align: top; text-align: left;}
			table.sinenomine td.key a {display: block;}
			table.sinenomine, table.lines {margin-top: 1.2em;}
			table.sinenomine th.text {min-width: 300px;}
			table.sinenomine td {text-align: left;}
			/* Lines table style */
			table.lines {border-collapse: collapse; /* width: 95%; */}
			.lines td, .lines th {border-bottom: 1px solid #e9e9e9; padding: 6px 4px 2px; vertical-align: top; text-align: left;}
			/* .lines td:first-child, .lines th:first-child {width: 150px;} */
			.lines tr:first-child {border-top: 1px solid #e9e9e9;}
			.lines td h3 {text-align: left; padding-top: 20px;}
			.lines p {text-align: left;}
			.lines td.noline {border-bottom: 0;}
			table.compressed td {padding: 0 4px;}
			table.spaced td {padding: 8px 4px;}
			table.alternate tr:nth-child(odd) td {background-color: #d8e7e9;}
			table.lines.regulated td.key {width: 150px;}
			.lines td.noline, table.noline td, table.lines.noline tr:first-child {border-bottom: 0; border-top: 0;}
			table.rightkey td.key {text-align: right;}
			/* Graybox */
			div.graybox {border: 1px solid #ddd; padding: 10px 15px; margin: 0 10px 10px 0; background-color: #fcfcfc;}
			div.graybox:hover {background-color: #fafafa; border-color: #aaa;}
			div.graybox h2, div.graybox h3 {margin-top: 0.4em;}
			div.graybox p {text-align: left; margin-top: 10px;}
			/* Pagination */
			p.paginationsummary {padding-bottom: 0; margin-bottom: 0; margin-top: 2em;}
			p.paginationlist {padding-top: 0; margin-top: 0.5em; line-height: 1.8em;}
			p.paginationlist span {display: none;}
			p.paginationlist a {color: #333;}
			p.paginationlist a, p.paginationlist strong {padding: 1px 5px; border: 1px solid #ccc;}
			p.paginationlist strong {border-color: #333;}
			p.paginationlist a.selected {background-color: #ddd;}
			p.paginationlist a:hover {border-color: #6100c1; background-color: #f0e1ff; text-decoration: none;}
			/* Icons */
			a.action {padding: 10px; background-repeat: no-repeat; background-position: 4px center; padding: 3px 4px 3px 24px; white-space: no-wrap;}
			a.button {background-color: #f7f7f7; border: 1px solid #ddd; -moz-border-radius: 3px; margin-right: 1px;}
			a.button:hover {background-color: #fafafa; border-color: #aaa;}
			a.add {background-image: url(/images/icons/add.png);}
			a.clone {background-image: url(/images/icons/application_double.png);}
			a.delete {background-image: url(/images/icons/cross.png);}
			a.edit {background-image: url(/images/icons/page_white_edit.png);}
			a.export {background-image: url(/images/icons/application_view_columns.png);}
			a.list {background-image: url(/images/icons/application_view_detail.png);}
			a.quicklist {background-image: url(/images/icons/application_side_list.png);}
			a.view {background-image: url(/images/icons/page.png);}
			/* Field limiting */
			div.limitfields {margin-top: 10px; border: 1px solid #ddd; padding: 2px 10px;}
			div.limitfields table.checkboxcolumns td {padding-top: 0; vertical-align: top;}
		';
		
		# Add the surrounding tags if required
		if ($addSurroundingTags) {
			$html  = '<style type="text/css" media="screen">' . "\n" . $html . '</style>';
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create a footer
	private function pageFooter ()
	{
		# End if not in GUI mode
		if (!$this->settings['gui']) {return false;}
		
		# End if no user
		if (!$this->user && !$this->credentialsUser) {return false;}
		
		# Otherwise use the default header
		$html = '
		
		
		<div id="footer">
			<p><a href="#">^ Top</a> | <a href="%baseUrl">Home</a> | <a href="%structureUrl">Structure</a>%logoutLink</p>
		</div>
	
	</div>
	
</body>
</html>';
		
		# Use a specified footer if required
		if ($this->settings['footerHtml']) {$html = $this->settings['footerHtml'];}
		
		# Substitute the breadcrumb trail
		$substitutions = array (
			'%baseUrl' => $this->baseUrl . '/',
			'%logoutLink' => ($this->user ? ' | <a href="' . $this->logoutUrl . '">Logout</a>' : ''),
			'%structureUrl' => $this->structureUrl,
		);
		$html = strtr ($html, $substitutions);
		
		
		# Return the header
		return $html;
	}
}

?>

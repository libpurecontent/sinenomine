<?php

#!# Joins to empty tables seem to revert to a standard input field rather than an empty SELECT list
#!# Joins needed, e.g. http://sinenomine.dnsalias.net/helpdesk/administrators/
#!# Create pagination UI
#!# Need to test data object editing version
#!# How are unknown field types dealt with?
#!# Description being wrongly styled, at: http://sinenomine.dnsalias.net/labs/equipment/1/edit.html
#!# Deal with uploads, e.g. http://sinenomine.dnsalias.net/labs/equipment/1/edit.html
#!# Password field is not editable, e.g. http://sinenomine.dnsalias.net/alumni/contacts/add.html
#!# Timestamp is not visible when intelligence switched on
#!# Ensure that BINARY values are not put into the HTML


# Class to deal with generic table editing; called 'sineNomine' which means 'without a name' in recognition of the generic use of this class
class sinenomine
{
	# Class variables
	var $database = NULL;
	var $table = NULL;
	var $record = NULL;
	var $databaseConnection = NULL;
	var $user = NULL;
	var $html = '';
	
	# Specify available arguments as defaults or as NULL (to represent a required argument)
	var $defaults = array (
		'hostname' => 'localhost',	// Whether to use internal logins
		'vendor'	=> 'mysql',
		'autoLogoutTime' => 1800,	// Number of seconds after which automatic logout will take place
		'database' => false,
		'table' => false,
		'baseUrl' => false,
		'do' => 'do',	// $_GET['do'] or something else for the main action, e.g. 'action' would look at $_GET['action']; 'do' is the default as it is less likely to clash
		'databaseUrlPart' => false,	// Whether to include the database in the URL *if* a database has been supplied in the settings
		'hostnameInTitle' => false,	// Whether to include the hostname in the <title> tag hierarchy
		//'administratorEmail'	=> $_SERVER['SERVER_ADMIN'], /* Defined below */	// Who receives error notifications (or false if disable notifications)
		'gui' => false,	// Whether to add GUI features rather than just be an embedded component
		'headerHtml' => false,	// Specific header HTML (rather than the default); ignored in non-GUI mode
		'footerHtml' => false,	// Specific footer HTML (rather than the default); ignored in non-GUI mode
		'excludeMetadataFields' => array ('Field', /*'Collation', 'Default',*/'Privileges'),
		'commentsAsHeadings' => true,	// Whether to use comments as headings if there are any comments
		'convertJoinsInView' => true,	// Whether to convert joins when viewing a table
		'clonePrefillsSourceKey' => true,	// Whether cloning should prefill the source record's key
		'displayErrorDebugging' => false,	// Whether to show error debug info on-screen
		'highlightMainTable'	=> true,	// Whether to make bold a table whose name is the same as the database
		'listingsShowTotals'	=> true,	// Whether to show the total number of records in each table when listings
		'attributes' => array (),
		'exclude' => array (),
		'validation' => array (),
		'deny' => false,	// Deny edit access to database(s)/table(s)
		'denyInformUser' => true,	// Whether to inform the user if a database/table is denied
		'denyAdministratorOverride' => true,	// Whether to allow administrators access to denied database(s)/table(s)
		'userIsAdministrator' => false,	// Whether the user is an administrator
		'includeOnly' => array (),
		'nullText' => '',	// ultimateForm defaults
		'cols' => 60,		// ultimateForm defaults
		'rows' => 5,		// ultimateForm defaults
		'lookupFunctionParameters' => array (),
		'refreshSeconds' => 0,	// Refresh time in seconds after editing an article
		'showViewLink' => false,	// Whether to show the redundant 'view' link in the record listings
		'compressWhiteSpace' => true,	// Whether to compress whitespace between table cells in the HTML
		'reserved' => array ('cluster', 'information_schema', 'mysql'),
		'logfile' => false,
		'application' => false,	// Name of a calling application
		'intelligence' => true,	// Whether to enable dataBinding intelligence
		'rewrite' => true,	// Whether mod_rewrite is on
	);
	
	
	# Specify available actions
	var $actions = array (
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
		'clone' => array (
			'description' => 'Clone a record',
		),
		'delete' => array (
			'description' => 'Delete a record',
		),
		'structure' => array (
			'description' => 'Database structure hierarchy',
			'administration' => true,
			'url' => '/structure.html',
			'urlQueryString' => '/?%do=structure',
		),
	);
	
	
	# Constructor
	function __construct ($settings = array (), $databaseConnection = NULL, &$html = NULL)
	{
		# Load required libraries
		require_once ('application.php');
		require_once ('database.php');
		// session.php is loaded below, as it depends on settings which are dependent on application.php
		
		# Add additional defaults
		$this->defaults['administratorEmail'] = $_SERVER['SERVER_ADMIN'];
		//$this->defaults['application'] = __CLASS__;
		
		# Start the HTML
		$this->html  = $html;
		
		# Merge in the arguments; note that $errors returns the errors by reference and not as a result from the method
		if (!$this->settings = application::assignArguments ($errors, $settings, $this->defaults, __CLASS__, NULL, $handleErrors = true)) {
			return false;
		}
		
		# Assign the base URL
		$this->baseUrl = ($this->settings['baseUrl'] ? $this->settings['baseUrl'] : application::getBaseUrl ());
		
		# Determine the action to take, using the default (index) if none supplied
		$this->action = (!isSet ($_GET[$this->settings['do']]) ? 'index' : (array_key_exists ($_GET[$this->settings['do']], $this->actions) ? $_GET[$this->settings['do']] : false));
		
		# Define a logout URL and Determine whether to log out
		$this->logoutUrl = $this->baseUrl . ($this->settings['rewrite'] ? $this->actions['logout']['url'] : str_replace ('%do', $this->settings['do'], $this->actions['logout']['urlQueryString']));
		$logout = ($this->action == 'logout');
		
		# Define other URLs
		$this->structureUrl = $this->baseUrl . ($this->settings['rewrite'] ? $this->actions['structure']['url'] : str_replace ('%do', $this->settings['do'], $this->actions['structure']['urlQueryString']));
		
		# In GUI mode, start a session to obtain credentials dynamically
		if ($this->settings['gui'] && $databaseConnection === NULL) {
			require_once ('session.php');
			$session = new session ($this->settings, $logout);
			
			# Redirect to the front page if logged out, having destroyed the session
			if ($logout) {
				header ('Location: http://' . $_SERVER['SERVER_NAME'] . $this->baseUrl);
				$mainHtml = "<p>You have been logged out. " . $this->createLink (NULL, NULL, NULL, NULL, 'Please click here to continue.') . '</p>';
			} else {
				$this->databaseConnection = $session->getDatabaseConnection ();
				$this->user = $session->getUser ();
				$mainHtml = $session->getHtml ();
			}
			
		# Otherwise create a connection
		} else {
			if (!$databaseConnection || !$databaseConnection->connection) {
				$mainHtml = $this->error ('No valid database connection was supplied.');
			} else {
				$this->databaseConnection = $databaseConnection;
			}
		}
		
		# Set up all the logic and then cache the main page HTML
		if ($this->databaseConnection) {
			$mainHtml = $this->main ();
			if ($this->settings['gui']) {$mainHtml = "\n\n\n\t<div id=\"content\">\n\n" . $mainHtml . "\n\t</div>";}
		}
		
		# Build the HTML
		$this->html .= $this->pageHeader ();
		$this->html .= $this->pageMenu ();
		$this->html .= $mainHtml;
		$this->html .= $this->pageFooter ();
		
		# In GUI mode, show the HTML directly
		if ($this->settings['gui']) {
			echo $this->html;
		}
		
		//# Notional return value (HTML is passed by reference)
		//return true;
	}
	
	
	# Function to set up the environment and take action
	function main ()
	{
		# Start the HTML
		$html  = '';
		
		# Determine if the user is an administrator
		$this->userIsAdministrator = $this->settings['userIsAdministrator'];
		
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
		$html = "\n<h2>" . htmlspecialchars ($this->actions[$this->action]['description']) . '</h2>';
		
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
		
		# End if no editable databases
		if (!$this->databases) {
			$html .= "\n<p>There are no databases" . (($this->settings['denyInformUser'] && $deniedDatabases) ? ' that you can edit' : '') . " in the system, so this editor cannot be used.</p>";
			return $html;
		}
		
		# Run specific functions
		if (isSet ($this->actions[$this->action]['administration'])) {
			$html .= $this->{$this->action} ();
			return $html;
		}
		
		# Ensure a database is supplied
		if (!$this->settings['database'] && !isSet ($_GET['database'])) {
			$html .= "\n<p>Please select a database:</p>";
			$html .= $this->linklist ($this->databases);
			return $html;
		}
		
		# Allocate the database, preferring settings over user-supplied data
		$this->database = ($this->settings['database'] ? $this->settings['database'] : $_GET['database']);
		
		# Tell the user if the current database is denied
		if ($this->settings['denyInformUser'] && in_array ($this->database, $deniedDatabases)) {
			$html .= sprintf ("\n<p>Access to the database <em>%s</em> has been denied by the administrator.</p>", htmlspecialchars ($this->database));
			$this->database = NULL;
			return $html;
		}
		
		# Ensure the database exists
		if (!in_array ($this->database, $this->databases)) {
			$this->database = NULL;
			$html .= "\n<p>There is no such database. Please select one:</p>";
			$html .= $this->linklist ($this->databases);
			return $html;
		}
		
		# Provide encoded versions of the database class variable for use in links
		$this->databaseEncoded = $this->doubleEncode ($this->database);
		$this->databaseEntities = htmlspecialchars ($this->database);
		
		# Get the available tables for this database
		$this->tables = $this->databaseConnection->getTables ($this->database);
		
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
		
		# Get the available tables for this database
		if (!$this->tables) {
			$html .= "\n<p>There are no tables" . (($this->settings['denyInformUser'] && $deniedTables) ? ' that you can edit' : '') . " in this database.</p>";
			return $html;
		}
		
		# Determine a link to database level
		$this->databaseLink = $this->createLink ($this->database, NULL, NULL, NULL, NULL, NULL, false);
		
		# Ensure a table is supplied
		if (!$this->settings['table'] && !isSet ($_GET['table'])) {
			$html .= "\n<p>Please select a table (or add [+] a record):</p>";
			$html .= $this->linklist ($this->database, $this->tables, false, $addAddLink = true, $this->settings['listingsShowTotals']);
			return $html;
		}
		
		# Allocate the table, preferring settings over user-supplied data
		$this->table = ($this->settings['table'] ? $this->settings['table'] : $_GET['table']);
		
		# Tell the user if the current database is denied
		if ($this->settings['denyInformUser'] && in_array ($this->table, $deniedTables)) {
			$html .= "\n<p>Access to the table <em>" . htmlspecialchars ($this->table) . '</em> in the database <em>' . $this->createLink ($this->database) . '</em> has been denied by the administrator.</p>';
			$this->table = NULL;
			return $html;
		}
		
		# Ensure the table exists
		if (!in_array ($this->table, $this->tables)) {
			$this->table = NULL;
			$html .= "\n<p>There is no such table. Please select one:</p>";
			$html .= $this->linklist ($this->database, $this->tables);
			return $html;
		}
		
		# Provide encoded versions of the table class variable for use in links
		$this->tableEncoded = $this->doubleEncode ($this->table);
		$this->tableEntities = htmlspecialchars ($this->table);
		
		# Determine a link to table level
		$this->tableLink = $this->createLink ($this->database, $this->table, NULL, NULL, NULL, NULL, false);
		
		# Get table status
		$this->tableStatus = $this->databaseConnection->getTableStatus ($this->database, $this->table);
		
		# Get the fields for this table
		if (!$this->fields = $this->databaseConnection->getFields ($this->database, $this->table)) {
			return $html .= $this->error ('There was some problem getting the fields for this table.');
		}
		
		# Get the joins for this table and add them into the fields list as well as creating an array of join data
		$this->joins = array ();
		foreach ($this->fields as $fieldname => $fieldAttributes) {
			if ($matches = $this->databaseConnection->convertJoin ($fieldAttributes['Field'])) {
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
			return $html .= $this->error ('This table appears not to have a unique key field.');
		}
		
		# Determine if the key is automatic (true/false, or NULL if no key)
		$this->keyIsAutomatic = ($this->key ? ($this->fields[$this->key]['Extra'] == 'auto_increment') : NULL);
		
		# Get record data
		$this->record = false;
		$this->recordEntities = false;
		$this->recordLink = false;
		$this->data = array ();
		if (isSet ($_GET['record'])) {
			#!# Still says 'view records' as the main title
			if ($this->action == 'index') {$this->action = 'record';}
			if (!$this->data = $this->databaseConnection->selectOne ($this->database, $this->table, array ($this->key => $_GET['record']))) {
				if ($this->action != 'add') {
					$html .= "\n<p>There is no such record <em>" . htmlspecialchars ($_GET['record']) . '</em>. Did you intend to ' . $this->createLink ($this->database, $this->table, $_GET['record'], 'add', 'create a new record' . ($this->keyIsAutomatic ? '' : ' with that key'), 'action button add') . '?</p>';
					return $html;
				}
			} else {
				$this->record = $_GET['record'];
				$this->recordEntities = htmlspecialchars ($this->record);
				$this->recordLink = $this->createLink ($this->database, $this->table, $this->record, NULL, NULL, NULL, false);
			}
		}
		
		# Take action
		if ($this->action == 'clone') {$this->action = 'cloneRecord';}	// 'clone' can't be used as a function name
		$html .= $this->{$this->action} ();
		return $html;
	}
	
	
	# Function to return the HTML
	function getHtml ()
	{
		# Return the HTML
		return $this->html;
	}
	
	
	# Function to create a breadcrumb trail
	function breadcrumbTrail ()
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
	function position ($hostname = true, $addPrefix = ': ', $convertEntities = true)
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
	function pageMenu ()
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
	function linklist ($databases, $tables = NULL, $current = false, $addAddLink = false, $listingsShowTotals = false, $tabs = 1)
	{
		# Determine which is being looped through (databases or tables)
		$items = (is_array ($databases) ? $databases : $tables);
		
		# Create the links
		$list = array ();
		foreach ($items as $index => $item) {
			
			# Show the number of records in the table if wanted
			#!# Ideally the bracketed section would have a span round it for styling
			$total = ((is_array ($tables) && $listingsShowTotals) ? ' (' . $this->databaseConnection->getTotalRecords ($databases, $item) . ')' : false);
			
			# Define the link for the current item
			if (is_array ($databases)) {
				$class = ($item == $this->database ? 'current' : false);
				$link = $this->createLink ($item, NULL, NULL, NULL, NULL, $class);
			} else {
				$class  = ($item == $this->table ? 'current' : false);
				if ($this->settings['highlightMainTable'] && ($item == $this->database)) {$class .= ($class ? ' ' : '') . 'maintable';}
				$label = $item;
				$link = $this->createLink ($databases, $item, NULL, NULL, $item . $total, $class);
			}
			
			# Add an [+] link afterwards if wanted
			if (is_array ($tables) && $addAddLink) {$link .= ' &nbsp;' . $this->createLink ($databases, $item, NULL, 'add', '[+]');}
			
			# Deal with nesting, i.e. if lists of both databases and tables are supplied, add the tables list at the 'current' item
			if (is_array ($databases) && is_array ($tables) && ($item == $current)) {
				$link .= $this->linkList ($item, $tables, $current, $addAddLink, $listingsShowTotals, ($tabs + 1));
			}
			
			# Add the item to the list
			$list[$index] = $link;
		}
		
		# Create the list
		$html = application::htmlUl ($list, $tabs);
		
		# Return the list
		return $html;
	}
	
	
	# Function to list an index of all records in a table, i.e. only the keys
	function index ($fullView = true)
	{
		# Start the HTML
		$html = '';
		
		# Determine the ordering, using a URL-supplied value if the fieldname exists
		$descending = (isSet ($_GET['direction']) && ($_GET['direction'] == 'desc'));
		$direction = ($descending ? ' DESC' : '');
		$orderBy = ((isSet ($_GET['orderby']) && array_key_exists ($_GET['orderby'], $this->fields)) ? $_GET['orderby'] : $this->key);
		$orderBySql = ((isSet ($_GET['orderby']) && array_key_exists ($_GET['orderby'], $this->fields)) ? "{$_GET['orderby']}{$direction},{$this->key}" : $this->key);
		
		# Get the data
		$query = 'SELECT ' . ($fullView ? '*' : $this->key) . " FROM `{$this->database}`.`{$this->table}` ORDER BY {$orderBySql}{$direction};";
		if (!$data = $this->databaseConnection->getData ($query, "{$this->database}.{$this->table}")) {
			$html .= "\n<p>There are no records in the <em>{$this->tableEntities}</em> table.</p>\n<p>You can " . $this->createLink ($this->database, $this->table, NULL, 'add', 'add a record', 'action button add') . '.</p>';
			return $html;
		}
		
		# Convert join data
		$data = $this->convertJoinDataRecord ($data, $this->fields);
		
		# Determine total records
		$total = count ($data);
		
		# Start a table, adding in metadata in full-view mode
		$table = array ();
		
		# Get the metadata names by taking the attributes of a known field, taking out unwanted fieldnames
		#!# Need to deal with clashing keys (the metadata fieldnames could be the same as real data); possible solution is not to allocate row keys but instead just use []
		if ($fullView) {
			
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
				if (ereg ('^_', $metadataField)) {continue;}
				
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
		foreach ($data as $key => $attributes) {
			$key = htmlspecialchars ($key);
			$table[$key]['Record'] = '<strong>' . $this->createLink ($this->database, $this->table, $key, NULL, $attributes[$this->key], 'action view') . '</strong>';
			if ($this->settings['showViewLink']) {
				$table[$key]['View'] = $this->createLink ($this->database, $this->table, $key, NULL, 'View', 'action view');
			}
			$actions = array ('edit', 'clone', 'delete');
			foreach ($actions as $action) {
				$title = ucfirst ($action);
				$table[$key][$title] = $this->createLink ($this->database, $this->table, $key, $action, ucfirst ($action), "action {$action}");
			}
			
			# Add all the data in full view mode
			if ($fullView) {
				foreach ($attributes as $field => $value) {
					$table[$key][$field] = str_replace (array ("\r\n", "\n"), '<br />', htmlspecialchars ($value));
				}
			}
		}
		
		# Convert fieldnames containing joins
		if ($fullView) {
			foreach ($table['Field'] as $fieldname => $label) {
				if (isSet ($this->fields[$fieldname]['_field'])) {
					$table['Field'][$fieldname] = "<abbr title=\"{$label}\">{$this->fields[$fieldname]['_field']}</abbr>&nbsp;&raquo;<br />" . $this->createLink ($this->fields[$fieldname]['_targetDatabase'], $this->fields[$fieldname]['_targetTable'], NULL, NULL, NULL, NULL, true, $asHtmlNewWindow = true, $asHtmlTableIncludesDatabase = true);
				}
			}
		}
		
		# Add the orderby links to the headings
		$headings = $this->headings;
		foreach ($headings as $field => $visible) {
			$fieldLink = ($this->key == $field ? false : 'orderby=' . $this->doubleEncode (htmlspecialchars ($field)));
			$selected = (($field == $orderBy) ? ' class="selected"' : '');
			$arrow = (($field == $orderBy) ? ($descending ? ' &uarr;' : ' &darr;') : '');
			$directionLink = ((($field == $orderBy) && !$descending) ? ($fieldLink ? '&amp;' : '') . 'direction=desc' : '');
			$headerLink = $this->createLink ($this->database, $this->table, NULL, NULL, NULL, NULL, false) . (($fieldLink || $directionLink) ? ($this->settings['rewrite'] ? '?' : '&amp;') . "{$fieldLink}{$directionLink}" : '');
			$headings[$field] = "<a href=\"{$headerLink}\"{$selected}>{$visible}{$arrow}</a>";
		}
		
		# Compile the HTML
		$totalFields = count ($this->fields);
		$html .= "\n<p>This table, " . $this->createLink ($this->database) . ".{$this->tableEntities}, contains <strong>" . ($total == 1 ? 'one record' : "{$total} records") . '</strong> (each with ' . ($totalFields == 1 ? 'one field' : "{$totalFields} fields") . '), as listed below. You can switch to ' . ($fullView ? $this->createLink ($this->database, $this->table, NULL, 'listing', 'quick index', 'action button quicklist') . ' mode.' : $this->createLink ($this->database, $this->table, NULL, NULL, 'full-entry view', 'action button list') . ' (default) mode.') . '</p>';
		$html .= "\n<p>You can also " . $this->createLink ($this->database, $this->table, NULL, 'add', 'add a record', 'action button add') . '.</p>';
		#!# Enable sortability
		// $html .= "\n" . '<!-- Enable table sortability: --><script language="javascript" type="text/javascript" src="http://www.geog.cam.ac.uk/sitetech/sorttable.js"></script>';
		#!# Add line highlighting, perhaps using js
		#!# Consider option to compress output using str_replace ("\n\t\t", "", $html) for big tables
		$html .= application::htmlTable ($table, $headings, ($fullView ? 'sinenomine' : 'lines'), false, false, true, false, $addCellClasses = false, $addRowKeys = true, array (), $this->settings['compressWhiteSpace']);
		
		# Show the table
		return $html;
	}
	
	
	# Function to urlencode a string and double-encode slashes
	function doubleEncode ($string)
	{
		# Return the string, double-encoding slashes only
		return rawurlencode (str_replace ('/', '%2f', $string));
	}
	
	
	# Function to show all records in a table in full
	function listing ()
	{
		return $this->index ($fullView = false);
	}
	
	
	# Function to view a record
	function record ($embed = false)
	{
		# Start the HTML
		$html  = '';
		
		# Do lookups
		$data = $this->convertJoinDataRecord ($this->data, $this->fields);
		
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
	function add ()
	{
		# Wrapper to editing a record but with the key taken out
		return $this->recordManipulation (__FUNCTION__);
	}
	
	
	# Function to clone a record
	function cloneRecord ()
	{
		# Wrapper to editing a record but with the key taken out
		return $this->recordManipulation ('clone');
	}
	
	
	# Function to edit a record
	function edit ()
	{
		# Edit the record
		return $this->recordManipulation (__FUNCTION__);
	}
	
	
	# Function to do record manipulation
	function recordManipulation ($action)
	{
		# Start the HTML
		$html = "<p>On this page you can {$action} " . ($action == 'add' ? 'a record to ' : 'record ' . $this->createLink ($this->database, $this->table, $this->record, NULL, $this->record, 'action button view') . ' in ') . $this->createLink ($this->database, $this->table, NULL, NULL, NULL, 'action button list') . '.</p>';
		
		#!# Lookup delete rights
		
		# Pre-fill the data
		$data = $this->data;
		
		# Prevent addition of a new record whose key already exists
		if ($action == 'add') {
			if ($data) {
				$html .= "\n<p>You cannot add a record " . $this->createLink ($this->database, $this->table, $this->record, NULL, $this->record, 'action button view') . ' as it already exists. You can ' . $this->createLink ($this->database, $this->table, $this->record, 'clone', 'clone that record', 'action button clone') . ' or ' . $this->createLink ($this->database, $this->table, NULL, 'add', 'create a new record', 'action button add') . '.</p>';
				return $html;
			}
			$data[$this->key] = (isSet ($_GET['record']) ? $_GET['record'] : '');
		}
		
		# Set whether the key is editable
		$keyAttributes['editable'] = (($action != 'edit') && !$this->keyIsAutomatic);
		
		# Deal with automatic keys (which will now be non-editable)
		if (($action != 'edit') && $this->keyIsAutomatic) {
			#!# The first four values are a workaround for just placing the text '(automatically assigned)'
			$keyAttributes['type'] = 'select';
			$keyAttributes['forceAssociative'] = true;
			$keyAttributes['default'] = 1;
			$keyAttributes['discard'] = true;
			$keyAttributes['values'] = array (1 => '(Automatically assigned)');	// The value '1' is used to ensure it always validates, whatever the field specification is
		}
		
		# If adding or cloning, get current values to ensure that it cannot be re-entered
		if ($action == 'add' || $action == 'clone') {
			$keyAttributes['current'] = $this->getCurrentKeys ();
		}
		
		# If cloning, NULL out the key value if required
		if ($action == 'clone' && !$this->settings['clonePrefillsSourceKey']) {
			$data[$this->key] = NULL;
		}
		
		# Determine the attributes
		$attributes = $this->parseOverrides ($this->settings['attributes']);
		$exclude = $this->parseOverrides ($this->settings['exclude']);
		$includeOnly = $this->parseOverrides ($this->settings['includeOnly']);
		$validation = $this->parseOverrides ($this->settings['validation']);
		
		# Merge in (override) the key handling
		$attributes[$this->key] = $keyAttributes;
		
		# Load and create a form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'databaseConnection' => $this->databaseConnection,
			'developmentEnvironment' => ini_get ('display_errors'),
			'displayRestrictions' => false,
			'formCompleteText' => false,
			'nullText' => $this->settings['nullText'],
			'cols' => $this->settings['cols'],
			'rows' => $this->settings['rows'],
			'div' => 'graybox lines',
		));
		$form->dataBinding (array (
			'database' => $this->database,
			'table' => $this->table,
			'data' => $data,
			'lookupFunction' => array ('database', 'lookup'),
			'lookupFunctionParameters' => $this->settings['lookupFunctionParameters'],
			'lookupFunctionAppendTemplate' => "<a href=\"{$this->baseUrl}/" . ($this->includeDatabaseUrlPart ? '%database/' : '') . "%table/\" class=\"noarrow\" title=\"Click here to open a new window for editing these values; then click on refresh.\" target=\"_blank\"> ...</a>%refresh",
			'includeOnly' => $includeOnly,
			'exclude' => $exclude,
			'attributes' => $attributes,
			'intelligence' => $this->settings['intelligence'],
		));
		
		# Add validation rules
		#!# This is pretty nasty API stuff; perhaps allow a direct passing as $sinenomine->form->validation instead
		if ($validation) {
			foreach ($validation as $validationRule) {
				$form->validation ($validationRule[0], $validationRule[1]);
			}
		}
		
		# Process the form
		if (!$record = $form->process ($html)) {
			return $html;
		}
		
		#!# VERY TEMPORARY HACK to get uploading working by flattening the output; basically a special 'database' output format is needed at ultimateForm level
		if ((isSet ($record['filename']) && isSet ($record['filename'][0]))) {$record['filename'] = $record['filename'][0];}
		
		#!# End here if no filename
		
		# Update the record
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
			$this->recordEntities = htmlspecialchars ($this->record);
			$this->recordLink = $this->createLink ($this->database, $this->table, $this->record, NULL, NULL, NULL, false);
		}
		
		# (Re-)fetch the data
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
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to get current key values
	function getCurrentKeys ()
	{
		# Get the current keys
		$query = "SELECT {$this->key} FROM {$this->database}.{$this->table} ORDER BY {$this->key}";
		$keys = $this->databaseConnection->getPairs ($query);
		
		# Return the keys
		return $keys;
	}
	
	
	# Function to return the current record
	function getRecord ()
	{
		# Return the value
		return $this->record;
	}
	
	
	# Function to deal with database error handling
	function error ($userErrorMessage = 'A database error occured.')
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
	
	
	# Function to do referential integrity checks
	function joinsTo ($database, $table, $record = false, $returnAsString = true)
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
				foreach ($fields as $field => $attributes) {
					
					# If a join is found, add it to the list
					if ($join = $this->databaseConnection->convertJoin ($field)) {
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
					$links[] = $this->createLink ($database, $table, $joinedRecord, NULL, NULL, $class = 'action button view', true, $asHtmlNewWindow = true);
				}
				
				# Compile the HTML
				$tableLinks[] = 'In ' . $this->createLink ($database, $table, NULL, NULL, NULL, NULL, true, $asHtmlNewWindow = true, $asHtmlTableIncludesDatabase = true) . ': ' . ((count ($joins) == 1) ? 'record' : 'records') . ' ' . implode (', ', $links);
			}
		}
		
		# Compile the HTML
		$html = "\n<p>You can't currently delete the record <em>" . $this->createLink ($this->database, $this->table, $record, NULL, NULL, $class = 'action button view') . "</em>, because the following join to it:</p>" . application::htmlUl ($tableLinks);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function dealing with consistent link creation, taking account of whether URL-rewriting is on
	function createLink ($database = NULL, $table = NULL, $record = NULL, $action = NULL, $labelSupplied = NULL, $class = NULL, $asHtml = true, $asHtmlNewWindow = false, $asHtmlTableIncludesDatabase = false, $tooltips = true)
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
			$link .= ($this->settings['rewrite'] ? $this->doubleEncode ($table) . '/' : (($database && $this->includeDatabaseUrlPart) ? '&amp;' : '') . 'table=' . $this->doubleEncode ($table));
			$label = ($asHtmlTableIncludesDatabase ? "{$database}.{$table}" : $table);
			$tooltip = ($asHtmlTableIncludesDatabase ? "Database &amp; table" : 'Table');
		}
		
		# Add the record if required
		if ($record !== NULL) {
			$link .= ($this->settings['rewrite'] ? $this->doubleEncode ($record) . '/' : '&amp;record=' . $this->doubleEncode ($record));
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
			$link .= ($this->settings['rewrite'] ? htmlspecialchars ($action) . '.html' : '&amp;do=' . $this->doubleEncode ($action));
			$tooltip = ucfirst ($action);
		}
		
		# Compile as HTML if necessary
		if ($asHtml) {
			$label = ($labelSupplied === NULL ? $label : $labelSupplied);
			$labelEntities = htmlspecialchars ($label);
			$title = array ();
			if ($tooltips) {$title[] = $tooltip;}
			if ($asHtmlNewWindow) {$title[] = '(Opens in a new window)';}
			$link = "<a href=\"{$link}\"" . ($asHtmlNewWindow ? " target=\"_blank\"" : '') . ($title ? ' title="' . implode (' ', $title) . '"' : '') . ($class ? " class=\"{$class}\"" : '') . ">{$labelEntities}</a>";
		}
		
		# Return the link
		return $link;
	}
	
	
	# Function to delete a record
	function delete ()
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
			'developmentEnvironment' => ini_get ('display_errors'),
			'displayRestrictions' => false,
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
		
		# Process the form
		if (!$result = $form->process ($html)) {
			return $html;
		}
		
		# Confirm that the record has not been deleted
		if (!$result['confirmation']) {
			$html .= "\n<p>The record " . $this->createLink ($this->database, $this->table, $this->record, NULL, $this->record, 'action button view') . ' has <strong>not</strong> been deleted.</p>';
			$html .= "\n<p>You may wish to " . $this->createLink ($this->database, $this->table, NULL, NULL, 'return to the list of records', $class = 'action button list') . '.</p>';
			return $html;
		}
		
		# Delete the record and confirm success
		if (!$this->databaseConnection->delete ($this->database, $this->table, array ($this->key => $this->record))) {
			return $html .= $this->error ();
		}
		$html .= "<p>The record <em>{$this->recordEntities}</em> in the table " . $this->createLink ($this->database, $this->table, NULL, NULL, NULL, $class = 'action button list') . ' has been deleted.</p>';
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to show a complete hierarchical listing of the database structure
	#!# Privileges?
	#!# Database limitation mode
	function structure ($hideReservedTables = true)
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
				
				# Create a list of tables and loop through them
				$fields = $this->databaseConnection->getFields ($database, $table);
				$listFieldItems = array ();
				foreach ($fields as $field) {
					
					# Construct HTML for the joins if one exists
					$join = $this->databaseConnection->convertJoin ($field['Field']);
					$joinedToHtml = ($join ? ('&nbsp;&nbsp;(joined to <a href="#' . htmlspecialchars ("{$database}.{$table}") . '">' . htmlspecialchars ("{$database}.{$table}") . '</a>)') : '');
					
					# Construct HTML showing the collation
					$collationHtml = '';
					if ($field['Collation']) {
						$collationHtml = ' [' . $field['Collation'] . ']';
						if ($field['Collation'] != 'utf8_unicode_ci') {
							$collationHtml = "<span class=\"warning\">{$collationHtml}</span>";
						}
					}
					
					# Add the field type
					$typeHtml = ' <span style="color: ' . ($field['Type'] == 'blob' ? 'red' : 'gray') . "\">{$field['Type']}</span>";
					
					# Add the comment
					$commentHtml = ($field['Comment'] ? " <em>'" . htmlspecialchars ($field['Comment']) . "'</em>" : '');
					
					# Add the field name to the list
					$fieldLink = $this->createLink ($database, $table, NULL, NULL, NULL, NULL, false) . ($field['Key'] ? '' : ($this->settings['rewrite'] ? '?' : '&amp;') . 'orderby=' . htmlspecialchars ($field['Field']));
					$listFieldItems[] = "<a href=\"{$fieldLink}\" title=\"Field\">" . ($field['Key'] ? '<strong>' : '') . htmlspecialchars ($field['Field']) . ($field['Key'] ? '</strong>' : '') . '</a>' . $joinedToHtml . $typeHtml . $collationHtml . $commentHtml;
				}
				
				# Add the table and its fields
				$listTableItems[] = '<a name="' . htmlspecialchars ("{$database}.{$table}") . '">' . ($this->settings['highlightMainTable'] && ($database == $table) ? '<strong>' : '') . $this->createLink ($database, $table, NULL, NULL, NULL, (($table == 'tallis') ? 'tallis' : '')) . ($this->settings['highlightMainTable'] && ($database == $table) ? '</strong>' : '') . application::htmlUl ($listFieldItems, 4, 'normal');
			}
			
			# Add the database and its tables
			$listDatabaseItems[] = '<a name="' . htmlspecialchars ($database) . '">' . $this->createLink ($database) . application::htmlUl ($listTableItems, 3, 'spaced');
		}
		
		# Add the server and its databases
		$listServerItems[] = $this->createLink () . application::htmlUl ($listDatabaseItems, 2);
		$html .= application::htmlUl ($listServerItems, 1);
		
		# Return the result
		return $html;
	}
	
	
	# Wrapper function for single records
	function convertJoinDataRecord ($data, $fields)
	{
		# Wrap the data in a container
		$records[0] = $data;
		
		# Convert the data
		$records = $this->convertJoinDataRecords ($records, $fields);
		
		# Remove the container
		$data = $records[0];
		
		# Return the data
		return $data;
	}
	
	
	# Function to convert key numbers/names in a set of records
	function convertJoinDataRecords ($data, $fields, /* $joins = NULL, */ /* $databaseConnection = false, */ $convertUrls = true, $showNumberFields = false)
	{
		/*
		# Get the database connection
		if ($databaseConnection) {
			$this->databaseConnection = $databaseConnection;
		}
		*/
		
		/*
		# Use the pre-computed joins, or compute them
		if ($joins === NULL) {
			foreach ($fields as $fieldname => $fieldAttributes) {
				if ($matches = $this->databaseConnection->convertJoin ($fieldAttributes['Field'])) {
					$joins[$fieldname] = $matches;
				}
			}
		}
		*/
		
		# Return the data unmodified if joins should not be looked up
		if (!$this->settings['convertJoinsInView']) {return $data;}
		
		# Return the data unmodified if there are no joins
		if (!$this->joins) {return $data;}
		
		# Return the data unodified if there is none or it is not an array
		if (!$data || (!is_array ($data))) {return $data;}
		
		# Start an array of values that need to be looked up
		$unconverted = array ();
		
		# Go through each record to get the unique values in the joined fields and create a list of them
		foreach ($data as $key => $record) {
			foreach ($record as $field => $value) {
				if (array_key_exists ($field, $this->joins)) {
					$unconverted[$field][$value] = $value;
				}
			}
		}
		
		# Perform the conversions for each unconverted value
		$conversions = array ();
		foreach ($unconverted as $field => $values) {
			
			# Get the unique field name for the target table, or skip if fails
			$uniqueField = $this->databaseConnection->getUniqueField ($this->joins[$field]['database'], $this->joins[$field]['table']);
			
			# Quote the values for use in a regexp
			#!# preg_quote doesn't necessary match MySQL REGEXP - need to check this
			foreach ($values as $key => $value) {
				$values[$key] = preg_quote ($value);
			}
			
			# Get the data
			$query = "SELECT * FROM `{$this->joins[$field]['database']}`.`{$this->joins[$field]['table']}` WHERE `{$uniqueField}` REGEXP '(^" . implode ('|', $values) . "$)';";
			$tempData = $this->databaseConnection->getData ($query, "{$this->joins[$field]['database']}.{$this->joins[$field]['table']}");
			
			# Compile the list
			foreach ($tempData as $key => $record) {
				$conversions[$field][$key] = implode (utf8_encode (' » '), $record);
			}
		}
		
		# Substitute in the conversions if they have been found
		foreach ($data as $key => $record) {
			foreach ($record as $field => $value) {
				if (array_key_exists ($field, $this->joins)) {
					$data[$key][$field] = (isSet ($conversions[$field][$value]) ? $conversions[$field][$value] : $value);
				}
			}
		}
		
		# Return the data
		return $data;
	}
	
	
	/*
	# Function to convert key numbers/names into the looked-up data
	#!# Rename to convertJoinDataRecord
	function convertJoinData ($record, $fields, $databaseConnection = false, $convertUrls = true, $showNumberFields = false)
	{
		# Get the database connection
		if ($databaseConnection) {
			$this->databaseConnection = $databaseConnection;
		}
		
		# Do lookups
		$uniqueFields = array ();
		$lookupValues = array ();
		foreach ($record as $fieldname => $value) {
			
			# Skip if no value
			if (empty ($value)) {continue;}
			
			# Convert the join or skip if not a join
			if (!$joins = $this->databaseConnection->convertJoin ($fieldname)) {continue;}
			
			# Get the unique field name for the target table, or skip if fails
			if (!isSet ($uniqueFields[$joins['database']][$joins['table']])) {
				if (!$uniqueField = $this->databaseConnection->getUniqueField ($joins['database'], $joins['table'])) {continue;}
				$uniqueFields[$joins['database']][$joins['table']] = $uniqueField;
			}
			
			# Lookup the data, or skip if fails
			if (!isSet ($lookupValues[$joins['database']][$joins['table']][$value])) {
				if (!$tempData = $this->databaseConnection->select ($joins['database'], $joins['table'], array ($uniqueFields[$joins['database']][$joins['table']] => $value), array (), false)) {continue;}
				$lookupValues[$joins['database']][$joins['table']][$value] = $tempData[0];
			}
			
			# Unset the key if numeric
			if (is_numeric ($lookupValues[$joins['database']][$joins['table']][$value][$uniqueField])) {
				unset ($lookupValues[$joins['database']][$joins['table']][$value][$uniqueField]);
			}
			
			# Compile the data, showing either all fields in the joined data, or the first $showNumberFields fields
			if (!$showNumberFields) {
				$value = implode (utf8_encode (' » '), $lookupValues[$joins['database']][$joins['table']][$value]);
			} else {
				$items = array ();
				$i = 0;
				foreach ($lookupValues[$joins['database']][$joins['table']][$value] as $key => $item) {
					if ($i == $showNumberFields) {break;}
					$items[] = $item;
					$i++;
				}
				$value = implode (utf8_encode (' » '), $items);
			}
			
			# Put the new value into the data
			$record[$fieldname] = $value;
		}
		
		# Modify display
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
			
			# Make entity-safe
//			# Make entity-safe if not a richtext field
//			if ($fields[$fieldname]['Type'] != 'text') {
				$value = htmlspecialchars ($value);
//			}
			
			# Replace line breaks
			#!# This should be disabled for richtext
			$value = str_replace ("\n", "<br />\n", $value);
			
			# Convert URLs if required
			#!# Bad smell: move this up the code chain
			if ($convertUrls) {
				require_once ('application.php');
				$value = application::makeClickableLinks ($value, false, '[Link]');
			}
			
			# Put the new value into the data
			$record[$fieldname] = $value;
		}
		
		# Return the data
		return $record;
	}
	*/
	
	
	# Function to modify display of a record
	function modifyDisplay ($record)
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
			
			# Make entity-safe
//			# Make entity-safe if not a richtext field
//			if ($fields[$fieldname]['Type'] != 'text') {
				$value = htmlspecialchars ($value);
//			}
			
			# Replace line breaks
			#!# This should be disabled for richtext
			$value = str_replace ("\n", "<br />\n", $value);
			
			# Convert URLs if required
			#!# Bad smell: move this up the code chain
			if ($convertUrls) {
				require_once ('application.php');
				$value = application::makeClickableLinks ($value, false, '[Link]');
			}
			
			# Put the new value into the data
			$record[$fieldname] = $value;
		}
		
		# Return the cleaned
		return $record;
	}
	
	
	# Function to create a footer
	function pageHeader ()
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
		h2 {font-size: 1.5em;}
		/* Links */
		a {text-decoration: none;}
		a:hover {text-decoration: underline;}
		/* Header */
		h1, h1 a, h1 a:visited {font-size: 1.4em; font-weight: normal; color: #bbb; text-decoration: none; margin-bottom: 0;}
		p#logout {position: absolute; right: 0; top: 0; margin-right: 20px;}
		/* Breadcrumb trail */
		p.locationline {margin-top: 5px; margin-bottom: 2.4em;}
		/* Lists */
		ul li {margin-top: 3px;}
		ul.spaced li, li.spaced {margin-top: 12px;}
		ul.normal li, li.normal {margin-top: 3px;}
		/* Forms */
		fieldset {border: 0; padding: 0;}
		td {padding: 10px 2px 0;}
		td.title {text-align: right; vertical-align: top;}
		.error, .warning {color: red;}
		.comment {color: gray;}
		.restriction, .description {color: #999; font-style: italic;}
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
		/* Icons */
		a.action {padding: 10px; background-repeat: no-repeat; background-position: 4px center; padding: 3px 4px 3px 24px; white-space: no-wrap;}
		a.button {background-color: #f7f7f7; border: 1px solid #ddd; -moz-border-radius: 3px; margin-right: 1px;}
		a.button:hover {background-color: #fafafa; border-color: #aaa;}
		a.add {background-image: url(/images/icons/add.png);}
		a.clone {background-image: url(/images/icons/application_double.png);}
		a.delete {background-image: url(/images/icons/cross.png);}
		a.edit {background-image: url(/images/icons/page_white_edit.png);}
		a.list {background-image: url(/images/icons/application_view_detail.png);}
		a.quicklist {background-image: url(/images/icons/application_side_list.png);}
		a.view {background-image: url(/images/icons/magnifier.png);}	/* page_go.png */
	</style>
	<script language="javascript" type="text/javascript">
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
	</script>
</head>
<body onload="setFocus()">

<div id="container">

	<div id="header">
		<h1><a href="%baseUrl">sineNomine data editor</a></h1>
		%logoutLink
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
			'%autoLogoutTime' => $this->settings['autoLogoutTime'] + 5,
			'%breadcrumbTrail' => $this->breadcrumbTrail (),
			'%position' => $this->position ($this->settings['hostnameInTitle']),
		);
		$html = strtr ($html, $substitutions);
		
		# Return the header
		return $html;
	}
	
	
	# Function to create a footer
	function pageFooter ()
	{
		# End if not in GUI mode
		if (!$this->settings['gui']) {return false;}
		
		# Otherwise use the default header
		$html = '
		
		
		<div id="footer">
			<p><a href="#">^ Top</a> | <a href="%baseUrl">Home</a> | <a href="%structureUrl">Structure</a>%logoutLink</p>
		</div>
	
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
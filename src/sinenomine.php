<?php

#!# Joins to empty tables seem to revert to a standard input field rather than an empty SELECT list



# Class to deal with generic table editing; called 'sineNomine' which means 'without a name' in recognition of the generic use of this class


class sinenomine
{
	# Class variables
	var $database = NULL;
	var $table = NULL;
	var $record = NULL;
	var $databaseConnection = NULL;
	
	
	# Specify available arguments as defaults or as NULL (to represent a required argument)
	var $defaults = array (
		'internalAuth' => true,	// Whether to use internal logins
		'database' => false,
		'table' => false,
		'baseUrl' => false,
		'databaseUrlPart' => false,	// Whether to include the database in the URL *if* a database has been supplied in the settings
		//'administratorEmail'	=> $_SERVER['SERVER_ADMIN'], /* Defined below */	// Who receives error notifications (or false if disable notifications)
		'showBreadcrumbTrail'	 => true,
		'excludeMetadataFields' => array ('Field', 'Collation', 'Default', 'Privileges'),
		'commentsAsHeadings' => true,	// Whether to use comments as headings if there are any comments
		'convertJoinsInView' => true,	// Whether to convert joins when viewing a table
		'clonePrefillsSourceKey' => false,	// Whether cloning should prefill the source record's key
		'displayErrorDebugging' => false,	// Whether to show error debug info on-screen
		'highlightMainTable'	=> true,	// Whether to make bold a table whose name is the same as the database
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
		'rows' => 4,		// ultimateForm defaults
		'lookupFunctionParameters' => array (),
		'refreshSeconds' => 0,	// Refresh time in seconds after editing an article
		'showViewLink' => false,	// Whether to show the redundant 'view' link in the record listings
		'compressWhiteSpace' => true,	// Whether to compress whitespace between table cells in the HTML
	);
	
	
	# Specify available actions
	var $actions = array (
		'index' => array (
			'description' => 'View records',
			'url' => '',
		),
		'listing' => array (
			'description' => 'Quick listing of all records',
			'url' => '',
		),
		'record' => array (
			'description' => 'View a record',
			'url' => '',
		),
		'add' => array (
			'description' => 'Add a record',
			'url' => '',
		),
		'edit' => array (
			'description' => 'Edit a record',
			'url' => '',
		),
		'clone' => array (
			'description' => 'Clone a record',
			'url' => '',
		),
		'delete' => array (
			'description' => 'Delete a record',
			'url' => '',
		),
	);
	
	
	# Function to assign defaults additional to the general application defaults
	function defaults ()
	{
		# Return the defaults
		return $this->defaults;
	}
	
	
	# Define supported editing actions
	function actions ()
	{
		# Return the actions
		return $this->actions;
	}
	
	
	# Constructor
	function __construct ($databaseConnection = NULL, $settings = array ())
	{
		# Start the HTML
		$html  = '';
		
		# Load required libraries
		require_once ('application.php');
		
		# Add additional defaults
		$this->defaults['administratorEmail'] = $_SERVER['SERVER_ADMIN'];
		$this->defaults['application'] = __CLASS__;
		
		# Merge in the arguments; note that $errors returns the errors by reference and not as a result from the method
		if (!$this->settings = application::assignArguments ($errors, $settings, $this->defaults, __CLASS__, NULL, $handleErrors = true)) {
			return false;
		}
		
		# Assign the base URL
		$this->baseUrl = ($this->settings['baseUrl'] ? $this->settings['baseUrl'] : application::getBaseUrl ());
		
		# Determine if the user is an administrator
		$this->userIsAdministrator = $this->settings['userIsAdministrator'];
		
		# Ensure any deny list is an array
		$this->settings['deny'] = application::ensureArray ($this->settings['deny']);
		
		# Remove the deny list if the user has suitable privileges
		if ($this->userIsAdministrator && $this->settings['denyAdministratorOverride']) {
			$this->settings['deny'] = false;
		}
		
		#!# Connect to the database if no database connection has been supplied
		
		
		# Make a cursory attempt to ensure there is a database connection
		if (!$databaseConnection->connection) {
			$html .= $this->error ('No valid database connection was supplied.');
		} else {
			
			# Make the database connection available
			$this->databaseConnection = $databaseConnection;
			
			# Provide encoded versions of particular class variables for use in pages
			$this->hostnameEntities = htmlspecialchars ($this->databaseConnection->hostname);
			
			# Set up the environment and take action, caching the HTML
			$actionHtml = $this->main ();
			
			# Build the HTML
			$html .= $this->breadcrumbTrail ();
			$html .= $actionHtml;
		}
		
		# Show the HTML
		#!# Add ability to return instead
		echo $html;
	}
	
	
	# Function to set up the environment and take action
	function main ()
	{
		# Start the HTML
		$html  = '';
		
		# Determine the action to take, using the default (index) if none supplied
		if (!$this->action = (!isSet ($_GET['do']) ? 'index' : (array_key_exists ($_GET['do'], $this->actions) ? $_GET['do'] : false))) {
			$html .= "\n<p>No valid action was specified.</p>";
			return $html;
		}
		
		# Determine whether links should include the database URL part
		$this->includeDatabaseUrlPart = (!$this->settings['database'] || ($this->settings['database'] && $this->settings['databaseUrlPart']));
		
		# Show title
		$html = "\n<h2>" . htmlspecialchars ($this->actions[$this->action]['description']) . '</h2>';
		
		# Get the available databases
		$this->databases = $this->databaseConnection->getDatabases ();
		
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
		
		# Ensure a database is supplied
		if (!$this->settings['database'] && !isSet ($_GET['database'])) {
			$html .= "\n<p>Please select a database:</p>";
			$html .= $this->linklist ($this->databases, $this->baseUrl);
			return $html;
		}
		
		# Allocate the database, preferring settings over user-supplied data
		$this->database = ($this->settings['database'] ? $this->settings['database'] : $_GET['database']);
		
		# Tell the user if the current database is denied
		if ($this->settings['denyInformUser'] && in_array ($this->database, $deniedDatabases)) {
			$html .= "\n<p>Access to the database <em>" . htmlspecialchars ($this->database) . '</em> has been denied by the administrator.</p>';
			$this->database = NULL;
			return $html;
		}
		
		# Ensure the database exists
		if (!in_array ($this->database, $this->databases)) {
			$this->database = NULL;
			$html .= "\n<p>There is no such database. Please select one:</p>";
			$html .= $this->linklist ($this->databases, $this->baseUrl);
			return $html;
		}
		
		# Provide encoded versions of the database class variable for use in links
		$this->databaseEncoded = rawurlencode ($this->database);
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
		$this->databaseLink = $this->createLink ($this->database, NULL, NULL, false);
		
		# Ensure a table is supplied
		if (!$this->settings['table'] && !isSet ($_GET['table'])) {
			$html .= "\n<p>Please select a table:</p>";
			$html .= $this->linklist ($this->tables, $this->databaseLink, ($this->settings['highlightMainTable'] ? $this->database : false));
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
			$html .= $this->linklist ($this->tables, $this->databaseLink, ($this->settings['highlightMainTable'] ? $this->database : false));
			return $html;
		}
		
		# Provide encoded versions of the table class variable for use in links
		$this->tableEncoded = rawurlencode ($this->table);
		$this->tableEntities = htmlspecialchars ($this->table);
		
		# Determine a link to table level
		$this->tableLink = $this->createLink ($this->database, $this->table, NULL, false);
		
		# Get table status
		$this->tableStatus = $this->databaseConnection->getTableStatus ($this->database, $this->table);
		
		# Get the fields for this table
		if (!$this->fields = $this->databaseConnection->getFields ($this->database, $this->table)) {
			return $html .= $this->error ('There was some problem getting the fields for this table.');
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
			if (!$data = $this->databaseConnection->select ($this->database, $this->table, array ($this->key => $_GET['record']))) {
				if ($this->action != 'add') {
					$html .= "\n<p>There is no such record <em>" . htmlspecialchars ($_GET['record']) . "</em>. Did you intend to <a href=\"{$this->tableLink}" . urlencode ($_GET['record']) . '/add.html">create a new record' . ($this->keyIsAutomatic ? '' : ' with that key') . '</a>?</p>';
					return $html;
				}
			} else {
				$this->data = $data[$_GET['record']];	// Effectively do a 'getOne' using a select
				$this->record = $_GET['record'];
				$this->recordEntities = htmlspecialchars ($this->record);
				$this->recordLink = $this->createLink ($this->database, $this->table, $this->record, false);
			}
		}
		
		# Take action
		if ($this->action == 'clone') {$this->action = 'cloneRecord';}	// 'clone' can't be used as a function name
		$html .= $this->{$this->action} ();
		return $html;
	}
	
	
	# Function to create a breadcrumb trail
	function breadcrumbTrail ()
	{
		# End if not required
		if (!$this->settings['showBreadcrumbTrail']) {return false;}
		
		# Construct the list of items, avoiding linking to the current page
		$items[] = "<a href=\"{$this->baseUrl}/\" title=\"Hostname\">{$this->hostnameEntities}</a>";
		if ($this->database) {$items[] = (($this->action == 'index' && !$this->table) ? "<span title=\"Database\">{$this->databaseEntities}</span>" : $this->createLink ($this->database));}
		if ($this->table) {$items[] = ($this->action == 'index' ? "<span title=\"Table\">{$this->tableEntities}</span>" : $this->createLink ($this->database, $this->table)) . ' <span class="comment"><em>(' . htmlspecialchars ($this->tableStatus['Comment']) . ')</em></span>';}
		if ($this->record) {$items[] = ($this->action == 'record' ? "<span title=\"Record\">{$this->recordEntities}</span>" : $this->createLink ($this->database, $this->table, $this->record));}
		
		# Compile the HTML
		$html = "\n\n<p class=\"locationline\">You are in: " . implode (' &raquo; ', $items) . '</p>';
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create a list of links
	function linklist ($data, $baseUrl = '', $highlightMainTable = false)
	{
		# Create the links
		$list = array ();
		foreach ($data as $index => $item) {
			$list[$index] = "<a href=\"{$baseUrl}" . rawurlencode ($item) . '/">' . htmlspecialchars ($item) . '</a>';
			if ($highlightMainTable && ($item == $highlightMainTable)) {$list[$index] = "<strong>{$list[$index]}</strong>";}
		}
		
		# Create the list
		$html = application::htmlUl ($list);
		
		# Return the list
		return $html;
	}
	
	
	# Function to list an index of all records in a table, i.e. only the keys
	function index ($fullView = true)
	{
		# Start the HTML
		$html = '';
		
		# Get the data
		$query = 'SELECT ' . ($fullView ? '*' : $this->key) . " FROM `{$this->database}`.`{$this->table}` ORDER BY {$this->key};";
		if (!$data = $this->databaseConnection->getData ($query, "{$this->database}.{$this->table}")) {
			$html .= "\n<p>There are no records in the <em>{$this->tableEntities}</em> table. You can <a href=\"{$this->tableLink}add.html\">add a record</a>.</p>";
			return $html;
		}
		
		# Determine total records
		$total = count ($data);
		
		# Start a table, adding in metadata in full-view mode
		$table = array ();
		
		# Get the metadata names by taking the attributes of a known field, taking out unwanted fieldnames
		#!# Need to deal with clashing keys (the metadata fieldnames could be the same as real data); possible solution is not to allocate row keys but instead just use []
		if ($fullView) {
			
			# Flag whether there are any comments
			$commentsFound = false;
			foreach ($this->fields as $fieldname => $attributes) {
				if ($attributes['Comment']) {$commentsFound = true;}
			}
			
			$metadataFields = array_keys ($this->fields[$this->key]);
			#!# Ideally this would be done case-insensitively in case of user default-setting errors
			$metadataFields = array_diff ($metadataFields, $this->settings['excludeMetadataFields']);
			#!# Ideally change 'Field' to 'Fieldname'
			if ($commentsFound) {$metadataFields = array_merge (array ('Field'), $metadataFields);}
			foreach ($metadataFields as $metadataField) {
				$table[$metadataField] = array ($metadataField . ':', '', '', '', '',); // Placeholders against the starting Record/View/edit/clone/delete link headings
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
		
		# Show the data, starting with the links
		#!# Consider converting to using createLink, though the following is safely encoded
		foreach ($data as $key => $attributes) {
			$key = htmlspecialchars ($key);
			$table[$key]['Record'] = '<strong>' . htmlspecialchars ($attributes[$this->key]) . '</strong>';
			$table[$key]['View'] = "<a href=\"{$this->tableLink}" . urlencode ($key) . '/">View</a>';
			$actions = array ('edit', 'clone', 'delete');
			foreach ($actions as $action) {
				$table[$key][$action] = "<a href=\"{$this->tableLink}" . urlencode ($key) . "/{$action}.html\">" . ucfirst ($action) . '</a>';
			}
			
			# Add all the data in full view mode
			if ($fullView) {
				foreach ($attributes as $field => $value) {
					$table[$key][$field] = $value;
				}
			}
		}
		
		# Convert fieldnames containing joins
		$joinsFound = false;
		foreach ($table['Field'] as $fieldname => $label) {
			if ($join = $this->databaseConnection->convertJoin ($label)) {
				$table['Field'][$fieldname] = "<abbr title=\"{$label}\">{$join['field']}</abbr>&nbsp;&raquo;<br />" . $this->createLink ($join['database'], $join['table'], NULL, true, $asHtmlNewWindow = true, $asHtmlTableIncludesDatabase = true);
				$joinsFound = true;
			}
		}
		
		# Compile the HTML
		$html .= "\n<p>There " . ($total == 1 ? "is one record" : "are {$total} records") . ", as listed below. You can switch to " . ($fullView ? "<a href=\"{$this->tableLink}listing.html\">quick index</a> mode." : "<a href=\"{$this->tableLink}\">full-entry view</a> (default) mode.") . '</p>';
		$html .= "\n<p>You can also <a href=\"{$this->tableLink}add.html\">add a record</a>.</p>";
		#!# Enable sortability
		// $html .= "\n" . '<!-- Enable table sortability: --><script language="javascript" type="text/javascript" src="http://www.geog.cam.ac.uk/sitetech/sorttable.js"></script>';
		#!# Add line highlighting, perhaps using js
		#!# Consider option to compress output using str_replace ("\n\t\t", "", $html) for big tables
		$html .= application::htmlTable ($table, $this->headings, ($fullView ? 'sinenomine' : 'lines'), false, true, true, false, $addCellClasses = false, $addRowKeys = true);
		
		# Show the table
		return $html;
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
		$data = ($this->settings['convertJoinsInView'] ? $this->convertJoinData ($this->data, $this->fields, $this->databaseConnection) : $this->data);
		
		# Create the HTML
		if (!$embed) {
			$html .= "\n<p>The record <em>{$this->recordEntities}</em> in the table " . $this->createLink ($this->database, $this->table) . ' is as shown below.</p>';
			$html .= "\n<p>You can <a href=\"{$this->recordLink}edit.html\">edit</a>, <a href=\"{$this->recordLink}clone.html\">clone</a> or <a href=\"{$this->recordLink}delete.html\">delete</a> this record.</p>";
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
		$html = '';
		
		#!# Lookup delete rights
		
		# Pre-fill the data
		$data = $this->data;
		
		# Prevent addition of a new record whose key already exists
		if ($action == 'add') {
			if ($data) {
				$html .= "<p>You cannot add a record <em>{$this->record}</em> as it already <a href=\"{$this->recordLink}\">exists</a>. You can <a href=\"{$this->recordLink}clone.html\">clone that record</a> or <a href=\"{$this->tableLink}add.html\">create a new record</a>.</p>";
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
			'lookupFunction' => array (__CLASS__, 'lookup'),
			'lookupFunctionParameters' => $this->settings['lookupFunctionParameters'],
			'lookupFunctionAppendTemplate' => "<a href=\"{$this->baseUrl}/" . ($this->includeDatabaseUrlPart ? '%database/' : '') . "%table/\" class=\"noarrow\" title=\"Click here to open a new window for editing these values; then click on refresh.\" target=\"_blank\"> ...</a>%refresh",
			'includeOnly' => $includeOnly,
			'exclude' => $exclude,
			'attributes' => $attributes,
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
		
		#!# Need to check that the unique key posted for a new record is not already in use; implement a new callback within the form
		
		# Update the record
		$databaseAction = ($action == 'edit' ? 'update' : 'insert');
		$parameterFour = ($databaseAction == 'update' ? array ($this->key => $this->record) : NULL);
		if (!$result = $this->databaseConnection->$databaseAction ($this->database, $this->table, $record, $parameterFour)) {
			return $html .= $this->error ();
		}
		
		# Get the last insert ID of an insert
		if ($databaseAction == 'insert') {
			$this->record = $this->databaseConnection->getLatestId ();
			$this->recordEntities = htmlspecialchars ($this->record);
			$this->recordLink = $this->createLink ($this->database, $this->table, $this->record, false);
		}
		
		# (Re-)fetch the data
		$data = $this->databaseConnection->select ($this->database, $this->table, array ($this->key => $this->record));
		$this->data = $data[$this->record];
		
		# Confirm success and show the record
		$html .= "\n<p>The record <a href=\"{$this->recordLink}\">{$this->recordEntities}</a> has been " . ($action == 'edit' ? 'updated' : 'created') . " successfully.";
		$html .= "\n<p>You can now <a href=\"{$this->recordLink}edit.html\">edit it further</a>, <a href=\"{$this->tableLink}\">list/view other records</a>, or <a href=\"{$this->tableLink}add.html\">add another record</a>.</p>";
		$html .= "\n<p>The record" . ($action == 'edit' ? ' now' : '') . ' reads:</p>';
		$html .= "\n\n" . $this->record ($embed = true);
		#!# Replace this with the proper way of doing this
		if ($this->settings['refreshSeconds']) {
			$html .= "\n<meta http-equiv=\"refresh\" content=\"{$this->settings['refreshSeconds']};url={$this->tableLink}\">";
		}
		
		# Return the HTML
		return $html;
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
		
		# Construct an e-mail message
		$message  = "A database error from {$this->settings['application']} » " . __CLASS__ . " occured.\nDebug details are:";
		$message .= "\n\n\nUser error message:\n{$userErrorMessage}";
		if ($this->databaseConnection) {
			$message .= "\n\n" . ucfirst ($this->databaseConnection->vendor) . " error number:\n{$error[1]}";
			$message .= "\n\nError text:\n{$error[2]}";
			if ($error['query']) {$message .= "\n\nQuery:\n" . $error['query'];}
		}
		$message .= "\n\nURL:\n" . $_SERVER['_PAGE_URL'];
		if ($_POST) {$message .= "\n\nData in \$_POST:\n" . print_r ($_POST, 1);}
		
		# Construct the on-screen error message
		$html  = "\n<p class=\"warning\">{$userErrorMessage}</p>";
		$html .= "\n<p>" . ($this->settings['administratorEmail'] ? 'This problem has been reported to' : 'Please report this problem to') . ' the Webmaster.</p>';
		if ($this->settings['displayErrorDebugging']) {$html .= "\n\n<p>Debugging details:</p><div class=\"graybox\">\n<pre>" . wordwrap (htmlspecialchars ($message)) . '</pre></div>';}
		
		# Report to the webmaster by e-mail if required
		if ($this->settings['administratorEmail']) {
			application::sendAdministrativeAlert ($this->settings['administratorEmail'], $this->settings['application'], "Database error in {$this->settings['application']} » " . __CLASS__, $message);
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
					$links[] = $this->createLink ($database, $table, $joinedRecord, true, $asHtmlNewWindow = true);
				}
				
				# Compile the HTML
				$tableLinks[] = 'In ' . $this->createLink ($database, $table, NULL, true, $asHtmlNewWindow = true, $asHtmlTableIncludesDatabase = true) . ': ' . ((count ($joins) == 1) ? 'record' : 'records') . ' ' . implode (', ', $links);
			}
		}
		
		# Compile the HTML
		$html = "\n<p>You can't currently delete the record <em>" . $this->createLink ($this->database, $this->table, $record, true, $asHtmlNewWindow = false) . "</em>, because the following join to it:</p>" . application::htmlUl ($tableLinks);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create a link to a database/table/record
	function createLink ($database, $table = NULL, $record = NULL, $asHtml = true, $asHtmlNewWindow = false, $asHtmlTableIncludesDatabase = false, $tooltips = true)
	{
		# Start with the base URL and the database URL if required
		$link = $this->baseUrl . ($this->includeDatabaseUrlPart ? '/' . rawurlencode ($database) : '') . '/';
		
		# Define the text
		$label = $database;
		$tooltip = 'Database';
		
		# Add the table if required
		if ($table) {
			$link .= rawurlencode ($table) . '/';
			$label = ($asHtmlTableIncludesDatabase ? "{$database}.{$table}" : $table);
			$tooltip = ($asHtmlTableIncludesDatabase ? "Database &amp; table" : 'Table');
		}
		
		# Add the record if required
		if ($record) {
			$link .= rawurlencode ($record) . '/';
			$label = $record;
			$tooltip = 'Record';
		}
		
		# Compile as HTML if necessary
		if ($asHtml) {
			$labelEntities = htmlspecialchars ($label);
			$title = array ();
			if ($tooltips) {$title[] = $tooltip;}
			if ($asHtmlNewWindow) {$title[] = '(Opens in a new window)';}
			$link = "<a href=\"{$link}\"" . ($asHtmlNewWindow ? " target=\"_blank\"" : '') . ($title ? ' title="' . implode (' ', $title) . '"' : '') . ">{$labelEntities}</a>";
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
		));
		
		# Form text/widgets
		$form->heading ('p', "Do you really want to delete record <em><a href=\"{$this->recordLink}\">{$this->recordEntities}</a></em>, whose data is shown below?");
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
		
		# Confirm that the record has not been deleted.
		if (!$result['confirmation']) {
			$html .= "<p>The <a href=\"{$this->recordLink}\">record</a> has <strong>not</strong> been deleted.</p>";
			return $html;
		}
		
		# Delete the record and confirm success
		if (!$this->databaseConnection->delete ($this->database, $this->table, array ($this->key => $this->record))) {
			return $html .= $this->error ();
		}
		$html .= "<p>The record <em>{$this->recordEntities}</em> in the table " . $this->createLink ($this->database, $this->table) . ' has been deleted.</p>';
		
		# Return the HTML
		return $html;
	}
	
	
	# Define a lookup function used for data binding
	#!# Remove this stub
	#!# Caching mechanism needed for repeated fields (and fieldnames as below), one level higher in the calling structure
	function lookup ($databaseConnection, $fieldName, $fieldType, $showKeys = false, $orderby = false, $sort = true, $group = true, $firstOnly = false)
	{
		return database::lookup ($databaseConnection, $fieldName, $fieldType, $showKeys, $orderby, $sort, $group, $firstOnly);
	}
	
	
	# Function to convert key numbers/names into the looked-up data
	function convertJoinData ($data, $fields, $databaseConnection = false, $convertUrls = true, $showNumberFields = false)
	{
		# Get the database connection
		if ($databaseConnection) {
			$this->databaseConnection = $databaseConnection;
		}
		
		# Do lookups
		$uniqueFields = array ();
		$lookupValues = array ();
		foreach ($data as $fieldname => $value) {
			
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
				$value = implode (' » ', $lookupValues[$joins['database']][$joins['table']][$value]);
			} else {
				$items = array ();
				$i = 0;
				foreach ($lookupValues[$joins['database']][$joins['table']][$value] as $key => $item) {
					if ($i == $showNumberFields) {break;}
					$items[] = $item;
					$i++;
				}
				$value = implode (' » ', $items);
			}
			
			# Put the new value into the data
			$data[$fieldname] = $value;
		}
		
		# Modify display
		foreach ($data as $fieldname => $value) {
			
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
			$data[$fieldname] = $value;
		}
		
		# Return the data
		return $data;
	}
}

?>

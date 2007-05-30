<?php


# Class to deal with generic table editing; called 'sineNomine' which means 'without a name' in recognition of the generic use of this class
class sinenomine
{
	# Define supported editing actions
	var $actions = array (
		'index' => 'View records',
		'listing' => 'Quick listing of all records',
		'record' => 'View a record',
		'add' => 'Add a record',
		'edit' => 'Edit a record',
		'clone' => 'Clone a record',
		'delete' => 'Delete a record'
	);
	
	# Define settings defaults
	var $defaults = array (
		'database' => false,
		'table' => false,
		'baseUrl' => false,
		'databaseUrlPart' => false,	// Whether to include the database in the URL *if* a database has been supplied in the settings
		'showBreadcrumbTrail'	 => true,
		'excludeMetadataFields' => array ('Field', 'Collation', 'Default', 'Privileges'),
		'commentsAsHeadings' => true,	// Whether to use comments as headings if there are any comments
		'nullText' => '',
	);
	
	# Class variables
	var $database = NULL;
	var $table = NULL;
	var $record = NULL;
	
	
	# Constructor
	function __construct ($databaseConnection = NULL, $settings = array ())
	{
		# Start the HTML
		$html  = '';
		
		# Load required libraries
		require_once ('application.php');
		
		# Merge in the arguments; note that $errors returns the errors by reference and not as a result from the method
		if (!$this->settings = application::assignArguments ($errors, $settings, $this->defaults, __CLASS__, NULL, $handleErrors = true)) {
			return false;
		}
		
		# Assign the base URL
		$this->baseUrl = ($this->settings['baseUrl'] ? $this->settings['baseUrl'] : application::getBaseUrl ());
		
		# Make a cursory attempt to ensure there is a database connection
		if (!$databaseConnection->connection) {
			$html .= "\n<p>No valid database connection was supplied.</p>";
		} else {
			
			# Make the database connection available
			$this->databaseConnection = $databaseConnection;
			
			# Provide encoded versions of particular class variables for use in pages
			$this->hostnameEntities = htmlentities ($this->databaseConnection->hostname);
			
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
		
		# Show title
		$html = "\n<h2>" . htmlentities ($this->actions[$this->action]) . '</h2>';
		
		# Get the available databases
		if (!$databases = $this->databaseConnection->getDatabases ()) {
			$html .= "\n<p>There are no databases in the system, so this editor cannot be used.</p>";
			return $html;
		}
		
		# Ensure a database is supplied
		if (!$this->settings['database'] && !isSet ($_GET['database'])) {
			$html .= "\n<p>No database has been selected. Please select one:</p>";
			$html .= $this->linklist ($databases, $this->baseUrl);
			return $html;
		}
		
		# Allocate the database, preferring settings over user-supplied data
		$this->database = ($this->settings['database'] ? $this->settings['database'] : $_GET['database']);
		
		# Ensure the database exists
		if (!in_array ($this->database, $databases)) {
			$html .= "\n<p>There is no such database. Please select one:</p>";
			$html .= $this->linklist ($databases, $this->baseUrl);
			return $html;
		}
		
		# Provide encoded versions of the database class variable for use in links
		$this->databaseEncoded = rawurlencode ($this->database);
		$this->databaseEntities = htmlentities ($this->database);
		
		# Get the available tables for this database
		if (!$tables = $this->databaseConnection->getTables ($this->settings['database'])) {
			$html .= "\n<p>There are no tables in this database.</p>";
			return $html;
		}
		
		#!# Have some way here to remove unwanted tables
		
		
		# Determine a link to database level
		$includeDatabaseUrlPart = ($this->settings['database'] && $this->settings['databaseUrlPart']);
		$this->databaseLink = $this->baseUrl . ($includeDatabaseUrlPart ? "/{$this->databaseEncoded}" : '');
		
		# Ensure a table is supplied
		if (!$this->settings['table'] && !isSet ($_GET['table'])) {
			$html .= "\n<p>No table has been selected. Please select one:</p>";
			$html .= $this->linklist ($tables, $this->databaseLink);
			return $html;
		}
		
		# Allocate the table, preferring settings over user-supplied data
		$this->table = ($this->settings['table'] ? $this->settings['table'] : $_GET['table']);
		
		# Ensure the table exists
		if (!in_array ($this->table, $tables)) {
			$html .= "\n<p>There is no such table. Please select one:</p>";
			$html .= $this->linklist ($tables, $this->databaseLink);
			return $html;
		}
		
		# Provide encoded versions of the table class variable for use in links
		$this->tableEncoded = rawurlencode ($this->table);
		$this->tableEntities = htmlentities ($this->table);
		
		# Determine a link to table level
		$this->tableLink = $this->databaseLink . "/{$this->tableEncoded}";
		
		# Get table status
		$this->tableStatus = $this->databaseConnection->getTableStatus ($this->database, $this->table);
		
		# Get the fields for this table
		if (!$this->fields = $this->databaseConnection->getFields ($this->database, $this->table)) {
			$html .= "\n<p>There was some problem getting the fields for this table.</p>";
			#!# Report to webmaster
			return $html;
		}
		
		# Get the unique field
		if (!$this->key = $this->databaseConnection->getUniqueField ($this->database, $this->table, $this->fields)) {
			$html .= "\n<p>This table appears not to have a unique key field.</p>";
			#!# Report to webmaster
			return $html;
		}
		
		# Get record data if required
		$this->record = false;
		$this->recordEncoded = false;
		$this->recordEntities = false;
		$this->recordLink = false;
		$this->data = array ();
		if (isSet ($_GET['record']) && $this->action != 'add') {
			if ($this->action == 'index') {$this->action = 'record';}
			if (!$data = $this->databaseConnection->select ($this->database, $this->table, array ($this->key => $_GET['record']))) {
				$html .= "\n<p>There is no such record. Did you intend to <a href=\"{$this->tableLink}/" . urlencode ($_GET['record']) . '/add.html">create a record with this key</a>?</p>';
				return $html;
			} else {
				$this->data = $data[$_GET['record']];	// Effectively do a 'getOne' using a select
				$this->record = $_GET['record'];
				$this->recordEncoded = rawurlencode ($_GET['record']);
				$this->recordEntities = htmlentities ($_GET['record']);
				$this->recordLink = $this->tableLink . "/{$this->recordEncoded}";
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
		$items[] = "<a href=\"{$this->baseUrl}/\" title=\"hostname\">{$this->hostnameEntities}</a>";
		if ($this->database) {$items[] = (($this->action == 'index' && !$this->table) ? "<span title=\"database\">{$this->databaseEntities}</span>" : "<a href=\"{$this->databaseLink}/\" title=\"database\">{$this->databaseEntities}</a>");}
		if ($this->table) {$items[] = ($this->action == 'index' ? "<span title=\"table\">{$this->tableEntities}</span>" : "<a href=\"{$this->tableLink}/\" title=\"table\">{$this->tableEntities}</a>") . ' <span class="comment"><em>(' . htmlentities ($this->tableStatus['Comment']) . ')</em></span>';}
		if ($this->record) {$items[] = ($this->action == 'record' ? "<span title=\"record\">{$this->recordEntities}</span>" : "<a href=\"{$this->recordLink}/\" title=\"record\">{$this->recordEntities}</a>");}
		
		# Compile the HTML
		$html = "\n\n<p>You are in: " . implode (' &raquo; ', $items) . '</p>';
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create a list of links
	function linklist ($data, $baseUrl = '')
	{
		# Create the links
		$list = array ();
		foreach ($data as $item) {
			$list[] = "<a href=\"{$baseUrl}/" . rawurlencode ($item) . '/">' . htmlentities ($item) . '</a>';
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
			$html .= "\n<p>There are no records in the <em>{$this->tableEntities}</em> table. You can <a href=\"{$this->tableLink}/add.html\">add a record</a>.</p>";
			return $html;
		}
		
		# Determine total records
		$total = count ($data);
		
		# Start a table, adding in metadata in full-view mode
		$table = array ();
		
		# Flag whether there are any comments
		$commentsFound = false;
		foreach ($this->fields as $fieldname => $attributes) {
			if ($attributes['Comment']) {$commentsFound = true;}
		}
		
		# Get the metadata names by taking the attributes of a known field, taking out unwanted fieldnames
		#!# Need to deal with clashing keys (the metadata fieldnames could be the same as real data); possible solution is not to allocate row keys but instead just use []
		if ($fullView) {
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
		foreach ($data as $key => $attributes) {
			$key = htmlentities ($key);
			$table[$key]['Record'] = '<strong>' . htmlentities ($attributes[$this->key]) . '</strong>';
			$table[$key]['View'] = "<a href=\"{$this->tableLink}/" . urlencode ($key) . '/">View</a>';
			$actions = array ('edit', 'clone', 'delete');
			foreach ($actions as $action) {
				$table[$key][$action] = "<a href=\"{$this->tableLink}/" . urlencode ($key) . "/{$action}.html\">" . ucfirst ($action) . '</a>';
			}
			
			# Add all the data in full view mode
			if ($fullView) {
				foreach ($attributes as $field => $value) {
					$table[$key][$field] = $value;
				}
			}
		}
		
		# Substitute the final headings with the text equivalents if there are comments
		$headings = array ();
		if ($fullView) {
			foreach ($this->fields as $field => $attributes) {
				$headings[$field] = (($this->settings['commentsAsHeadings'] && $commentsFound && !empty ($attributes['Comment'])) ? $attributes['Comment'] : $field);
			}
		}
		
		# Compile the HTML
		$html .= "\n<p>There " . ($total == 1 ? "is one record" : "are {$total} records") . ", as listed below. You can switch to " . ($fullView ? "<a href=\"{$this->tableLink}/listing.html\">quick index</a> mode." : "<a href=\"{$this->tableLink}/\">full-entry view</a> (default) mode.") . '</p>';
		$html .= "\n<p>You can also <a href=\"{$this->tableLink}/add.html\">add a record</a>.</p>";
		#!# Enable sortability
		// $html .= "\n" . '<!-- Enable table sortability: --><script language="javascript" type="text/javascript" src="http://www.geog.cam.ac.uk/sitetech/sorttable.js"></script>';
		#!# Add line highlighting, perhaps using js
		#!# Consider option to compress output using str_replace ("\n\t\t", "", $html) for big tables
		$html .= application::htmlTable ($table, $headings, ($fullView ? 'sinenomine' : 'lines'), false, true, true, false, $addCellClasses = false, $addRowKeys = true);
		
		# Show the table
		return $html;
	}
	
	
	# Function to show all records in a table in full
	function listing ()
	{
		return $this->index ($fullView = false);
	}
	
	
	# Function to view a record
	function record ()
	{
		# Create the HTML
		$html = application::htmlTableKeyed ($this->data);
		
		# Return the HTML
		return $html;
	}
	
	
	# Wrapper function to provide the editing form
	function recordForm (&$html, $data = array (), $exclude = array ())
	{
		# Load and create a form
		require_once ('ultimateForm-dev.php');
		$form = new form (array (
			'databaseConnection' => $this->databaseConnection,
			'developmentEnvironment' => ini_get ('display_errors'),
			'displayRestrictions' => false,
			'formCompleteText' => false,
			'nullText' => $this->settings['nullText'],
		));
		
		# Determine if the key field should be editable
		$keyIsEditable = (!$data || $this->action == 'cloneRecord');
		#!# Deal with automatic key if adding or cloning a record
/*
		$keyIsAutomatic = ($this->fields[$this->key]['Extra'] == 'auto_increment');
		$exclude = ($keyIsAutomatic ? array ($this->key) : array ());
*/
		
		
		
		# Databind the form
		$form->dataBinding (array (
			'database' => $this->database,
			'table' => $this->table,
			'data' => $data,
			'lookupFunction' => array (__CLASS__, 'lookup'),
			#!# Make the key visible but non-editable if a way can be found
			'exclude' => $exclude,
			'attributes' => array (
				$this->key => array ('editable' => $keyIsEditable),	// Deal with the key field
//				$this->key => array ('editable' => !$keyIsAutomatic, 'required' => true, 'default' => ($keyIsAutomatic ? '(This is automatically assigned)' : '')),	// Deal with the key field
			),
		));
		
		# Process the form
		return $result = $form->process ($html);
	}
	
	
	# Function to add a record
	function add ()
	{
		# Start the HTML
		$html = '';
		
		# If a record number has been supplied, pass that through
		#!# Need to check that the ID being supplied is a valid type so that the form is actually submittable
		$data = (isSet ($_GET['record']) ? array ($this->key => $_GET['record']) : array ());
		
		# Hand off to the record form
		if (!$result = $this->recordForm ($html, $data)) {
			return $html;
		}
		
		application::dumpData ($result);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to edit a record
	function edit ($clone = false)
	{
		# Start the HTML
		$html = '';
		
		# If cloning, unset the key
		$data = $this->data;
		if ($clone) {
			unset ($data[$this->key]);
		}
		
		# Hand off to the record form, supplying the data
		if (!$result = $this->recordForm ($html, $data)) {
			return $html;
		}
		
		application::dumpData ($result);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to clone a record
	function cloneRecord ()
	{
		# Wrapper to editing a record but with the key taken out
		return $this->edit ($clone = true);
	}
	
	
	# Function to delete a record
	function delete ()
	{
		# Create the HTML
		$html  = "\n<p>Do you really want to delete record <em>{$this->recordEntities}</em>, whose data is shown below?</p>";
		
		// Webform here
		
		$html .= $this->view ();
		
		# Delete the record if wanted
		
		
		# Return the HTML
		return $html;
	}
	
	
	# Define a lookup function used for data binding
	function lookup ($databaseConnection, $fieldName, $fieldType, $sort = true, $showKeys = NULL)
	{
		# Determine if it's a special JOIN field
		$values = array ();
		if (eregi ('^([a-zA-Z0-9]+)__JOIN__([a-zA-Z0-9]+)__([a-zA-Z0-9]+)__reserved$', $fieldName, $matches)) {
			
			# Assign the new fieldname
			$fieldName = $matches[1];
			
			# Get the data
			#!# Enable recursive lookups
			#!# Enable ordering
			$query = "SELECT * FROM {$matches[2]}.{$matches[3]};";
			$allData = $databaseConnection->getData ($query, "{$matches[2]}.{$matches[3]}");
			
			# Show the keys if not a numeric fieldtype
			$showKey = (!is_null ($showKeys) ? $showKeys : (!strstr ($fieldType, 'int(')));
			
			# Convert the data into a single key/value pair, removing repetition of the key if required
			foreach ($allData as $key => $data) {
				#!# This assumes the key is the first ...
				array_shift ($data);
				$values[$key] = ($showKey ? "{$key}: " : '') . implode (' - ', array_values ($data));
			}
		}
		
		# Sort
		if ($sort) {ksort ($values);}
		
		# Return the field name and the lookup values
		return array ($fieldName, $values);
	}
}

?>

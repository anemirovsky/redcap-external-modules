## External Modules Official Documentation

"External Modules" is a class-based framework for plugins and hooks in REDCap. Modules can utilize any of the "REDCap" class methods (e.g., \REDCap::getData), and they also come with many other helpful built-in methods to store and manage settings for a given module. The documentation provided on this page will be useful for anyone creating an external module.

If you have created a module and wish to share it with the REDCap community, you may submit it to the [REDCap External Modules Submission Survey](https://redcap.vanderbilt.edu/surveys/?s=X83KEHJ7EA). If your module gets approved after submission, it will become available for download by any REDCap administrator from the [REDCap Repo](https://redcap.vanderbilt.edu/consortium/modules/).

### Naming a module

Modules must follow a specific naming scheme for the module directory that will sit on the REDCap web server. Each version of a module will have its own directory (like REDCap) and will be located in the `/redcap/modules/` directory on the server. A module directory name consists of three parts: 
1. a **unique name** (so that it will not duplicate any one else's module in the consortium) in [snake case](https://en.wikipedia.org/wiki/Snake_case) format
1. "_v" = an underscore + letter "v"
1. a **module version number in X.Y or X.Y.Z format** that consists of a major version X and minor version Y (e.g., 1.0 or 3.25), and in some cases a sub-minor version Z if in X.Y.X format (e.g., 1.0.0 or 3.25.2), in which X, Y, and Z must be an integer beginning with 0 and going up to 100 at most.

The diagram below shows the general directory structure of some hypothetical  modules to illustrate how modules will sit on the REDCap web server alongside other REDCap files and directories.
```
redcap
|-- modules
|   |-- mymodulename_v1.0.0
|   |-- other_module_v2.9
|   |-- other_module_v2.10
|   |-- other_module_v2.11
|   |-- yet_another_module_v1.5.3
|-- redcap_vX.X.X
|-- redcap_connect.php
|-- ...
```

### Module requirements

**Every module must have two files at the minimum:** 1) the module's PHP class file, in which the file name will be different for every module (e.g., `MyModuleClass.php`), and 2) the `config.json` file. The config file must be in JSON format, and must always be explictly named "*config.json*". The config file will contain all the module's basic configuration, such as its title, author information, module permissions, and many other module settings. The module class file houses the basic business logic of the module, and it can be named whatever you like so long as the file name matches the class name (e.g., Votecap.php contains the class Votecap). 

#### 1) Module class

Each module must define a module class that extends `ExternalModules\AbstractExternalModule` (see the example below).  Your module class is the central PHP file that will run all the business logic for the module. You may have many other PHP files (classes or include files), as well as JavaScript, CSS, etc. All other such files are optional, but the module class itself is necessary and drives the module.

``` php
<?php
// Set the namespace defined in your config file
namespace MyModuleNamespace\MyModuleClass;

// Declare your module class, which must extend AbstractExternalModule 
class MyModuleClass extends \ExternalModules\AbstractExternalModule {
     // Your module methods, constants, etc. go here
}
```

A module's class name can be named whatever you wish. The module class file must also have the same name as the class name (e.g., **MyModuleClass.php** containing the class **MyModuleClass**). Also, the namespace is up to you to name. Please note that the full namespace declared in a module must exactly match the "namespace" setting in the **config.json** file (with the exception of there being a double backslash in the config file because of escaping in JSON). For example, while the module class may have `namespace MyModuleNamespace\MyModuleClass;`, the config file will have `"namespace": "MyModuleNamespace\\MyModuleClass"`.

#### 2) Config.json

The config.json file provides all the basic configuration information for the module in JSON format. At the minimum, the config file must have the following defined: **name, namespace, description, and authors**. `name` is the module title, and `description` is the longer description of the module (typically between one sentence and a whole paragraph in length), both of which are displayed in the module list on the module manager page. Regarding `authors`, if there are several contributors to the module, you can provide multiple authors whose names get displayed below the module description on the Control Center's module manager page.

The PHP `namespace` of your module class must also be specified in the config file, and it must have a sub-namespace. Thus the overall namespace consists of two parts. The first part is the main namespace, and the second part is the sub-namespace. **It is required that the sub-namespace matches the module's class name (e.g., MyModuleClass).** The first part of the namespace can be any name you want, but you might want to use the name of your institution as a way of grouping the modules that you and your team create (e.g., `namespace Vanderbilt\VoteCap;`). That's only a suggestion though. Using namespacing with sub-namespacing in this particular way helps prevent against collisions in PHP class naming when multiple modules are being used in REDCap at the same time. 

Example of the minimum requirements of the config.json file:

``` json
{
   "name": "Example Module",
   "namespace": "MyModuleNamespace\\MyModuleClass", 
   "description": "This is a description of the module, and will be displayed below the module name in the user interface.",
   "authors": [
       {
            "name": "Jon Snow",
            "email": "jon.snow@vumc.org",
            "institution": "Vanderbilt University Medical Center"
        }
    ]
}
```

Below is a *mostly* comprehensive list of all items that can be added to the  **config.json** file. Remember that all items in the file must be in JSON format, which includes making sure that quotes and other characters get escaped properly. **An extensive example of config.json is provided at the very bottom of this page** if you wish to see how all these items will be structured.
* Module **name**
* Module  **description**
* **documentation** can be used to provide a filename or URL for the "View Documentation" link in the module list.  If this setting is omitted, the first filename that starts with "README" will be used if it exists.  If a markdown file is used, it will be automatically rendered as HTML.
* For module **authors**, enter their **name**,  **email**, and **institution**. At least one author is required to run the module.
* Grant **permissions** for all of the operations, including hooks (e.g., **redcap_save_record**).
* **links** specify any links to show up on the left-hand toolbar. These include stand-alone webpages (substitutes for plugins) or links to outside websites. These are listable at the control-center level or at the project level.  A **link** consists of:
	* A **name** to be displayed on the site
	* An **icon** in REDCap's image repository
	* A **url** either in the local directory or external to REDCap.
* **system-settings** specify settings configurable at the system-wide level (this Control Center).  Settings do NOT have to be defined in config.json to be used programmatically.  
* **project-settings** specify settings configurable at the project level, different for each project.  Settings do NOT have to be defined in config.json to be used programatically.  
* A setting consists of:
	* A **key** that is the unique identifier for the item. Dashes (-'s) are preferred to underscores (_'s).
	* A **name** that is the plain-text label for the identifier. You have to tell your users what they are filling out.
	* **required** is a boolean to specify whether the user has to fill this item out in order to use the module.
	* **type** is the data type. Available options are: 
		* text
		* textarea
		* descriptive
		* json
		* rich-text
		* field-list
		* user-role-list
		* user-list
		* dag-list
		* dropdown
		* checkbox
		* project-id
		* form-list
		* event-list
		* sub_settings
		* radio
		* file
	* **choices** consist of a **value** and a **name** for selecting elements (dropdowns, radios).
	* **super-users-only** can be set to **true** to only allow super users to access a given setting.
	* **repeatable** is a boolean that specifies whether the element can repeat many times. **If it is repeatable (true), the element will return an array of values.**
	* **autocomplete** is a boolean that enables autocomplete on dropdown fields.
	* **branchingLogic** is an structure which represents a condition or a set of conditions that defines whether the field should be displayed. See examples at the end of this section.
	* When type = **sub_settings**, the sub_settings element can specify a group of items that can be repeated as a group if the sub_settings itself is repeatable. The settings within sub_settings follow the same specification here.  It is also possible to nest sub_settings within sub_settings.
	* As a reminder, true and false are specified as their actual values (true/false not as the strings "true"/"false"). Other than that, all values and variables are strings.
	* **DEPRECATED (for now): Default values do NOT currently work consistently, and will likely need to be re-implemented.** Both project-settings and system-settings may have a **default** value provided (using the attribute "default"). This will set the value of a setting when the module is enabled either in the project or system, respectively.
* If your JSON is not properly specified, an Exception will be thrown.

#### Examples of branching logic

A basic case.

``` json
"branchingLogic": {
    "field": "source1",
    "value": "123"
}
```

Specifying a comparison operator (valid operators: "=", "<", "<=", ">", ">=", ">", "<>").

``` json
"branchingLogic": {
    "field": "source1",
    "op": "<",
    "value": "123"
}
```

Multiple conditions.

``` json
"branchingLogic": {
    "conditions": [
        {
            "field": "source1",
            "value": "123"
        },
        {
            "field": "source2",
            "op": "<>",
            "value": ""
        }
    ]
}
```

Multiple conditions - "or" clause.

``` json
"branchingLogic": {
    "type": "or",
    "conditions": [
        {
            "field": "source1",
            "op": "<=",
            "value": "123"
        },
        {
            "field": "source2",
            "op": ">=",
            "value": "123"
        }
    ]
}
```

Obs.: when `op` is not defined, "=" is assumed. When `type` is not defined, "and" is assumed.


### How to call REDCap Hooks

One of the more powerful things that modules can do is to utilize REDCap Hooks, which allow you to execute PHP code in specific places in REDCap. For general information on REDCap hook functions, see the hook documentation. **Before you can utilize a hook in your module, you must explicitly set permissions for the hook in your config.json file**, as seen in the example below. Simply provide the hook function name in the "permissions" array in the config file.

``` json
{
   "permissions": [
	"redcap_project_home_page",
	"redcap_control_center"
   ]
}
```

Next, you must **name a method in your module class the exact same name as the name of the hook function**. For example, in the HideHomePageEmails class below, there is a method named `redcap_project_home_page`, which means that when REDCap calls the redcap_project_home_page hook, it will execute the module's redcap_project_home_page method.

``` php
<?php 
namespace Vanderbilt\HideHomePageEmails;

class HideHomePageEmails extends \ExternalModules\AbstractExternalModule 
{
    // This method will be called by the redcap_data_entry_form hook
    function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance) 
    {
	// Put your code here to get executed by the hook
    }
}
```

Remember that each hook function has different method parameters that get passed to it (e.g., $project_id), so be sure to include the correct parameters as seen in the hook documentation for the particular hook function you are defining in your module class.

##### Every Page Hooks
By default, every page hooks will only execute on project specific pages (and only on projects with the module enabled).  However, you can allow them to execute on all system pages as well by setting the following flag in config.json:

`"enable-every-page-hooks-on-system-pages": true`

##### Extra hooks provided by External Modules
There are a few extra hooks dedicated for modules use:

- `redcap_module_system_enable($version)`: Triggered when a module gets enabled on Control Center.
- `redcap_module_system_disable($version)`: Triggered when a module gets disabled on Control Center.
- `redcap_module_system_change_version($version, $old_version)`: Triggered when a module version is changed.
- `redcap_module_project_enable($version, $project_id)`: Triggered when a module gets enabled on a specific project.
- `redcap_module_project_disable($version, $project_id)`: Triggered when a module gets disabled on a specific project.
- `redcap_module_configure_button_display($project_id)`: Triggered when each enabled module defined is rendered.  Return `null` if you don't want to display the Configure button and `true` to display.
- `redcap_module_link_check_display($project_id, $link)`: Triggered when each link defined in config.json is rendered.  Override this method and return `null` if you don't want to display the link, or modify and return the `$link` parameter as desired.  This method also controls whether pages will load if users access their URLs directly.
- `redcap_module_save_configuration($project_id)`: Triggered after a module configuration is saved.

Examples:

``` php
<?php

function redcap_module_system_enable($version) {
    // Do stuff, e.g. create DB table.
}

function redcap_module_system_change_version($version, $old_version) {
    if ($version == 'v2.0') {
        // Do stuff, e.g. update DB table.
    }
}

function redcap_module_system_disable($version) {
    // Do stuff, e.g. delete DB table.
}
```

### How to create plugin pages for your module

A module can have plugin pages (or what resemble traditional REDCap plugins). They are called "plugin" pages because they exist as a new page (i.e., does not currently exist in REDCap), whereas a hook runs in-line inside of an existing REDCap page/request. 

The difference between module plugin pages and traditional plugins is that while you would typically navigate directly to a traditional plugin's URL in a web browser (e.g., https://example.com/redcap/plugins/votecap/pluginfile.php?pid=26), module plugins cannot be accessed directly but can only be accessed through the External Modules framework's directory (e.g., https://example.com/redcap/redcap_vX.X.X/ExternalModules/?prefix=your_module&page=pluginfile&pid=26). Thus it is important to note that PHP files in a module's directory (e.g., /redcap/modules/votecap/pluginfile.php) cannot be accessed directly from the web browser.

Note: If you are building links to plugin pages in your module, you should use the  `getUrl()` method (documented in the methods list below), which will build the URL all the required parameters for you.

**Add a link on the project menu to your plugin:** Adding a page to your module is fairly easy. First, it requires adding an item to the `links` option in the config.json file. In order for the plugin link to show up in a project where the module is enabled, put the link settings (name, icon, and url) under the `project` sub-option, as seen below, in which *url* notes that index.php in the module directory will be the endpoint of the URL, *"VoteCap"* will be the link text displayed, and *brick.png* in the REDCap version's image directory will be used as the icon (this is optional). You may add as many links as you wish.  By default, project links will only display for superusers and users with design rights, but this can be customized in each module (see the *redcap_module_link_check_display()* documentation above). 

``` json
{
   "links": {
      "project": [
         {
            "name": "VoteCap",
            "icon": "brick",
            "url": "index.php"
         }
      ]
   }
}
```

**Adding links to the Control Center menu:**
If you want to similarly add links to your plugins on the Control Center's left-hand menu (as opposed to a project's left-hand menu), then you will need to add a `control-center` section to your `links` settings, as seen below.

``` json
{
   "links": {
      "project": [
         {
            "name": "VoteCap",
            "icon": "brick",
            "url": "index.php"
         }
      ],
      "control-center": [
         {
            "name": "VoteCap System Config",
            "icon": "brick",
            "url": "config.php"
         }
      ]
   }
}
```
**Disabling authentication in plugins:** If a plugin page should not enforce REDCap's authentication but instead should be publicly viewable to the web, then in the config.json file you need to 1) **append `?NOAUTH` to the URL in the `links` setting**, and then 2) **add the plugin file name to the `no-auth-pages` setting**, as seen below. Once those are set, all URLs built using `getUrl()` will automatically append *NOAUTH* to the plugin URL, and when someone accesses the plugin page, it will know not to enforce authentication because of the *no-auth-pages* setting. Otherwise, External Modules will enforce REDCap authentication by default.

``` json
{
   "links": {
      "project": [
         {
            "name": "VoteCap",
            "icon": "brick",
            "url": "index.php?NOAUTH"
         }
      ]
   },
   "no-auth-pages": [
      "index"
   ],
}
```

The actual code of your plugin page will likely reference methods in your module class. It is common to first initiate the plugin by instantiating your module class and/or calling a method in the module class, in which this will cause the External Modules framework to process the parameters passed, discern if authentication is required, and other initial things that will be required before processing the plugin and outputting any response HTML (if any) to the browser.

**Example plugin page code:**
``` php
<?php
// A $module variable will automatically be available and set to an instance of your module.
// It can be used like so:
$value = $module->getProjectSetting('my-project-setting');
// More things to do here, if you wish
```

### Available developer methods in External Modules

Listed below are the publicly supported methods that module creators may utilize in their modules. **DO NOT** reference any other methods or files (like the *ExternalModules* class) as they could change at any time.  If there is a method not listed here that you believe should be supported, feel free to request it via a GitHub issue or pull request. 

#### PHP Module Object
Since each module's main class will extend *AbstractExternalModule*.  The following built-in methods are available on each module's main class.  They can be called by using **$this** (e.g., `$this->getModuleName()`).

Method  | Description
------- | -----------
addAutoNumberedRecord([$pid]) | Creates the next auto numbered record and returns the record id.  If the optional PID parameter is not specified, the current PID will be automatically detected.
createDAG($name) | Creates a DAG with the specified name, and returns it's ID.
delayModuleExecution() | Pushes the execution of the module to the end of the queue; helpful to wait for data to be processed by other modules; execution of the module will be restarted from the beginning.  A boolean is returned that is true if hook was successfully delayed, or false if this is the module's last chance to perform any required actions.
deleteDAG($dagId) | Given a DAG ID, deletes the DAG and all Users and Records assigned to it.
disableUserBasedSettingPermissions() | By default an exception will be thrown if a set/remove setting method is called and the current user doesn't have access to change that setting.  Call this method in a module's constructor to disable this behavior and leave settings permission checking up to the module itself.
exitAfterHook() | Calling this method inside of a hook will schedule PHP's exit() function to be called after ALL modules finish executing for the current hook.  Do NOT call die() or exit() manually afterward (the framework will call it for you).
getChoiceLabel($params) | Given an associative array, get the label associated with the specified choice value for a particular field. See the following example:<br> $params = array('field_name'=>'my_field', 'value'=>3, 'project_id'=>1, 'record_id'=>3, 'event_id'=>18, 'survey_form'=>'my_form', 'instance'=>2);
getChoiceLabels($fieldName[, $pid]) | Returns an array mapping all choice values to labels for the specified field.
getConfig() | get the config for the current External Module; consists of config.json and filled-in values
getFieldLabel($fieldName) | Returns the label for the specified field name.
getModuleDirectoryName() | get the directory name of the current external module
getModuleName() | get the name of the current external module
getModulePath() | Get the path of the current module directory (e.g., /var/html/redcap/modules/votecap_v1.1/)
getProjectId() | A convenience method for returning the current project id. 
getProjectSetting($key&nbsp;[,&nbsp;$pid]) | Returns the value stored for the specified key for the current project if it exists.  If no value is set, null is returned.  In most cases the project id can be detected automatically, but it can optionally be specified as the third parameter instead.
getProjectSettings([$pid]) | Gets all project settings as an array.  Useful for cases when you may be creating a custom config page for the external module in a project.
getPublicSurveyUrl() | Returns the public survey url for the current project.
getQueryLogsSql($sql) | Returns the raw SQL that would run if the supplied parameter was passed into **queryLogs()**. 
getRecordId() | Returns the current record id if called from within a hook that includes the record id.
getSettingConfig($key) | Returns the configuration for the specified setting.
getSettingKeyPrefix() | This method can be overridden to prefix all setting keys.  This allows for multiple versions of settings depending on contexts defined by the module.
getSubSettings($key) | Returns the sub-settings under the specified key in a user friendly array format.
getSystemSetting($key) | Get the value stored systemwide for the specified key.
getUrl($path [, $noAuth=false [, $useApiEndpoint=false]]) | Get the url to a resource (php page, js/css file, image etc.) at the specified path relative to the module directory. A `$module` variable representing an instance of your module class will automatically be available in PHP files.  If the $noAuth parameter is set to true, then "&NOAUTH" will be appended to the URL, which disables REDCap's authentication for that PHP page (assuming the link's URL in config.json contains "?NOAUTH"). Also, if you wish to obtain an alternative form of the URL that does not contain the REDCap version directory (e.g., https://example.com/redcap/redcap_vX.X.X/ExternalModules/?prefix=your_module&page=index&pid=33), then set $useApiEndpoint=true, which will return a version-less URL using the API end-point (e.g., https://example.com/redcap/api/?prefix=your_module&page=index&pid=33). Both links will work identically.
getUserSetting($key) | Returns the value stored for the specified key for the current user and project.  Null is always returned on surveys and NOAUTH pages.
hasPermission($permissionName) | checks whether the current External Module has permission for $permissionName
initializeJavascriptModuleObject() | Returns a JavaScript block that initializes the JavaScript version of the module object (documented below). 
isSurveyPage() | Returns true if the current page is a survey.  This is primarily useful in the **redcap_every_page_before_render** and **redcap_save_record** hooks.
log($message[, $parameters]) | Inserts a log entry including a message and optional array of key-value pairs for later retrieval using the **queryLogs()** method, and returns the inserted **log_id**.  Some parameters/columns are stored automatically, even if the **$parameters** argument is omitted (see **queryLogs()** for more details).
query($sql) | A thin wrapper around REDCap's db_query() that includes automatic error detection and reporting. 
queryLogs($sql) | Queries log entries added via the **log()** method using SQL-like syntax with the "from" portion omitted, and returns a MySQL result resource (just like **mysql_query()**).  Queries can include standard "select", "where", "order by", and "group by" clauses.  Available columns include **log_id**, **timestamp**, **user**, **ip**, **project_id**, **record**, **message**, and any parameter name passed to the **log()** method.  All columns must be specified explicitly ("select \*" syntax is not supported).  The raw SQL being executed by this method can be retrieved by calling **getQueryLogsSql()**.  Here are some query examples:*<br><br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;select timestamp, user where message = 'some message'<br><br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;select message, ip<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;where<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;timestamp > '2017-07-07'<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;and user in ('joe', 'tom')<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;or some_parameter like '%abc%'<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;order by timestamp desc*<br><br>If the `external_module_id` or `project_id` columns are not specified in the where clause, queries are limited to the current module and project (if detected) by default.  For complex queries, the log table can be manually queried (this method does not have to be used). 
removeLogs($sql) | Removes log entries matching the current module, current project (if detected), and the specified sql "where" clause.
removeProjectSetting($key&nbsp;[,&nbsp;$pid]) | Remove the value stored for this project and the specified key.  In most cases the project id can be detected automatically, but it can optionaly be specified as the third parameter instead. 
removeSystemSetting($key) | Removes the value stored systemwide for the specified key.
removeUserSetting($key) | Removes the value stored for the specified key for the current user and project.  This method does nothing on surveys and NOAUTH pages.
renameDAG($dagId, $name) | Renames the DAG with the given ID to the specified name.
saveFile($filePath[, $pid]) | Saves a file and returns the new edoc id.
setDAG($record, $dagId) | Sets the DAG for the given record ID to given DAG ID.
setData($record, $fieldName, $values) | Sets the data for the given record and field name to the specified value or array of values.
setProjectSetting($key,&nbsp;$value&nbsp;[,&nbsp;$pid]) | Sets the setting specified by the key to the specified value for this project.  In most cases the project id can be detected automatically, but it can optionally be specified as the third parameter instead.
setProjectSettings($settings[, $pid]) | Saves all project settings (to be used with getProjectSettings).  Useful for cases when you may create a custom config page or need to overwrite all project settings for an external module.
setSystemSetting($key,&nbsp;$value) | Set the setting specified by the key to the specified value systemwide (shared by all projects).
setUserSetting($key, $value) |  Sets the setting specified by the key to the given value for the current user and project.  This method does nothing on surveys and NOAUTH pages.  
validateSettings($settings) | Override this method in order to validate settings at save time.  If a non-empty error message string is returned, it will be displayed to the user, and settings will NOT be saved. 

### JavaScript Module Object
A JavaScript version of the module object can be created by calling the PHP module object's `initializeJavascriptModuleObject()` method at any point in any hook.  It will generate a JavaScript object matching the following pattern:
 
```ExternalModules.PHPNamespace.PHPClassName```

For example, the `log()` JavaScript method can be called on the supplied configuration example modules as follows:

```
<script>
	ExternalModules.Vanderbilt.ConfigurationExampleExternalModule.log('Hello from JavaScript!')
</script>

```

Method  | Description
------- | -----------
log() | See the description for the PHP version of this method (above).

### Utilizing Cron Jobs for Modules

Modules can actually have their own cron jobs that are run at a given interval by REDCap (alongside REDCap's internal cron jobs). This allows modules to have processes that are not run in real time but are run in the background at a given interval. There is no limit on the number of cron jobs that a module can have, and each can be configured to run at different times for different purposes. 

Module cron jobs must be defined in the config.json as seen below, in which each has a `cron_name` (alphanumeric name that is unique within the module), a `cron_description` (text that describes what the cron does), and a `method` (refers to a PHP method in the module class that will be executed when the cron is run). The `cron_frequency` and `cron_max_run_time` must be defined as integers (in units of seconds). The cron_max_run_time refers to the maximum time that the cron job is expected to run (once that time is passed, if the cron is still listed in the state of "processing", it assumes it has failed/crashed and thus will automatically enable it to run again at the next scheduled interval). Note: If any of the cron attributes are missing, it will prevent the module from being enabled.

``` json
{
   "crons": [
      {
         "cron_name": "cron1",
         "cron_description": "Cron that runs every 30 minutes to do X",
         "method": "cron1",
         "cron_frequency": "1800",
         "cron_max_run_time": "60"
      },
      {
         "cron_name": "cron2",
         "cron_description": "Cron that runs daily to do Y",
         "method": "some_other_method",
         "cron_frequency": "86400",
         "cron_max_run_time": "1200"
      }
   ]
}
```

### Module compatibility with specific versions of REDCap and PHP

It may be the case that a module is not compatible with specific versions of REDCap or specific versions of PHP. In this case, the `compatibility` option can be set in the config.json file using any or all of the four options seen below. (If any are listed in the config file but left blank as "", they will just be ignored.) Each of these are optional and should only be used when it is known that the module is not compatible with specific versions of PHP or REDCap. You may provide PHP min or max version as well as the REDCap min or max version with which your module is compatible. If a module is downloaded and enabled, these settings will be checked during the module enabling process, and if they do not comply with the current REDCap version and PHP version of the server where it is being installed, then REDCap will not be allow the module to be enabled.

``` json
{	
   "compatibility": {
      "php-version-min": "5.4.0",
      "php-version-max": "5.6.2",
      "redcap-version-min": "7.0.0",
      "redcap-version-max": ""
   }
}
```

### JavaScript recommendations

If your module will be using JavaScript, it is *highly recommended* that your JavaScript variables and functions not be placed in the global scope. Doing so could cause a conflict with other modules that are running at the same time that might have the same variable/function names. As an alternative, consider creating a function as an **IIFE (Immediately Invoked Function Expression)** or instead creating the variables/functions as properties of a **single global scope object** for the module, as seen below.

```
<script type="text/javascript">
  // IIFE - Immediately Invoked Function Expression
  (function($, window, document) {
      // The $ is now locally scoped

      // The rest of your code goes here!

  }(window.jQuery, window, document));
  // The global jQuery object is passed as a parameter
</script>
```

```
<script type="text/javascript">
  // Single global scope object containing all variables/functions
  var MCRI_SurveyLinkLookup = {};
  MCRI_SurveyLinkLookup.modulevar = "Hello world!";
  MCRI_SurveyLinkLookup.sayIt = function() {
    alert(this.modulevar);
  };
  MCRI_SurveyLinkLookup.sayIt();
</script>
```

### Other useful things to know

If the module class contains the __construct() method, you **must** be sure to call `parent::__construct();` as the first thing in the method, as seen below.

``` php
class MyModuleClass extends AbstractExternalModule {
   public function __construct()
   {
      parent::__construct();
      // Other code to run when object is instantiated
   }
}
```

### Including Dependencies/Libraries in your Module
If your module uses a third party library (i.e. PHPMailer) that is available in the [packagist.org](https://packagist.org) repo, please use [composer](https://getcomposer.org/) to include it.  While this adds an extra step when submitting to the module repo, it greatly reduces the chances of conflicts between modules that can cause those modules and/or REDCap to crash.  Composer's class loader automatically handles cases like calling **require** for the same class from multiple modules.  While this does mean that modules could potentially end up using the version of a dependency from another module instead of their own, this is rarely an issue in practice (as evidenced by the WordPress community's reliance on composer for plugin & theme dependencies).  Implementing a more complex dependency management system similar to Drupal's has been discussed, but such an effort is not likely since the current method is generally not an issue in practice.

If you would like to create a library to share between multiple modules, [composer can also use github as a repo](https://getcomposer.org/doc/05-repositories.md#loading-a-package-from-a-vcs-repository).

### Example config.json file

For reference, below is a nearly comprehensive example of the types of things that can be included in a module's config.json file.

``` json
{
   "name": "Configuration Example",

   "namespace": "Vanderbilt\\ConfigurationExampleExternalModule",

   "description": "Example module to show off all the options available",
   
   "documentation": "README.pdf",

   "authors": [
      {
         "name": "Jon Snow",
         "email": "jon.snow@vumc.org",
         "institution": "Vanderbilt University Medical Center"
      },
      {
         "name": "Arya Stark",
         "email": "arya.stark@vumc.org",
         "institution": "Vanderbilt University Medical Center"
      }
   ],

   "permissions": [
      "redcap_save_record",
      "redcap_data_entry_form"
   ],

   "enable-every-page-hooks-on-system-pages": false,

   "links": {
      "project": [
         {
            "name": "Configuration Page",
            "icon": "report",
            "url": "configure.php"
         }
      ],
      "control-center": [
         {
            "name": "SystemConfiguration Page",
            "icon": "report",
            "url": "configure_system.php"
         }
      ],
   },

   "no-auth-pages": [
      "public-page"
   ],

   "system-settings": [
      {
         "key": "system-file",
         "name": "System Upload",
         "required": false,
         "type": "file",
         "repeatable": false
      },
      {
         "key": "system-checkbox",
         "name": "System Checkbox",
         "required": false,
         "type": "checkbox",
         "repeatable": false
      },
      {
         "key": "system-project",
         "name": "Project",
         "required": false,
         "type": "project-id",
         "repeatable": false
      },
      {
         "key": "test-list",
         "name": "List of Sub Settings",
         "required": true,
         "type": "sub_settings",
         "repeatable":true,
         "sub_settings":[
            {
               "key": "system_project_sub",
               "name": "System Project",
               "required": true,
               "type": "project-id"
            },
            {
               "key": "system_project_text",
               "name": "Sub Text Field",
               "required": true,
               "type": "text"
            }
         ]
      }
   ],

   "project-settings": [
      {
         "key": "descriptive-text",
         "name": "This is just a descriptive field with only static text and no input field.",
         "type": "descriptive"
      },
      {
         "key": "instructions-field",
         "name": "Instructions text box",
         "type": "textarea"
      },
      {
         "key": "custom-field1",
         "name": "Custom Field 1",
         "type": "custom",
         "source": "js/test_javascript.js",
         "functionName": "ExternalModulesOptional.customTextAlert"
      },
      {
         "key": "custom-field2",
         "name": "Custom Field 2",
         "type": "custom",
         "source": "extra_types.js",
         "functionName": "ExternalModulesOptional.addColorToText"
      },
      {
         "key": "test-list2",
         "name": "List of Sub Settings",
         "required": true,
         "type": "sub_settings",
         "repeatable":true,
         "sub_settings":[
            {
            "key": "form-name",
            "name": "Form name",
            "required": true,
            "type": "form-list"
            },
            {
               "key": "arm-name",
               "name": "Arm name",
               "required": true,
               "type": "arm-list"
            },
            {
               "key": "event-name",
               "name": "Event name",
               "required": true,
               "type": "event-list"
            },
            {
            "key": "test-text",
            "name": "Text Field",
            "required": true,
            "type": "text"
            }
         ]
      },
      {
         "key": "text-area",
         "name": "Text Area",
         "required": true,
         "type": "textarea",
         "repeatable": true
      },
      {
         "key": "rich-text-area",
         "name": "Rich Text Area",
         "type": "rich-text"
      },
      {
         "key": "field",
         "name": "Field",
         "required": false,
         "type": "field-list",
         "repeatable": false
      },
      {
         "key": "dag",
         "name": "Data Access Group",
         "required": false,
         "type": "dag-list",
         "repeatable": false
      },
      {
         "key": "user",
         "name": "Users",
         "required": false,
         "type": "user-list",
         "repeatable": false
      },
      {
         "key": "user-role",
         "name": "User Role",
         "required": false,
         "type": "user-role-list",
         "repeatable": false
      },
      {
         "key": "file",
         "name": "File Upload",
         "required": false,
         "type": "file",
         "repeatable": false
      },
      {
         "key": "checkbox",
         "name": "Test Checkbox",
         "required": false,
         "type": "checkbox",
         "repeatable": false
      },
      {
         "key": "project",
         "name": "Other Project",
         "required": false,
         "type": "project-id",
         "repeatable": false
      }
   ],
   "crons": [
      {
         "cron_name": "cron1",
         "cron_description": "Cron that runs every 30 minutes to do X",
         "method": "cron1",
         "cron_frequency": "1800",
         "cron_max_run_time": "60"
       },
      {
         "cron_name": "cron2",
         "cron_description": "Cron that runs daily to do Y",
         "method": "some_other_method",
         "cron_frequency": "86400",
         "cron_max_run_time": "1200"
      }
   ],
   "compatibility": {
      "php-version-min": "5.4.0",
      "php-version-max": "5.6.2",
      "redcap-version-min": "7.0.0",
      "redcap-version-max": ""
   }
}
```

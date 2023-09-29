# Nr TextDB

## Installation
Require the package.

```bash
composer require netresearch/nr-textdb
```

## Configuration
You can set the PID for your translations in the extension configuration.
You can also switch off the "create if missing feature" if you want.

## Export and Import

The TextDB offer the ability to import and export translations. 

### Import

#### General
To Import translations they have to be placed in a xlf file which name should be in the following format.
For `en` name the file `textdb_[name].xlf` for all other languages use e.g. `de.textdb_[name].xlf` and replace `de` by the
two-letter iso code of your desired language you will import the translations for. 

#### File content

the content of the file you like to import should look like. 

File content for language EN.
```xml
<?xml version="1.0" encoding="utf-8" standalone="yes" ?>
<xliff version="1.0">
    <file source-language="en" datatype="plaintext" original="messages">
        <header>
            <authorName>Netresearch</authorName>
            <authorEmail>test@netresearch.de</authorEmail>
        </header>
        <body>
            <trans-unit id="component|type|placeholder">
                <source>Value</source>
            </trans-unit>
        </body>
    </file>
</xliff>
```

File content for all other languages.
```xml
<?xml version="1.0" encoding="utf-8" standalone="yes" ?>
<xliff version="1.0">
    <file source-language="en" datatype="plaintext" original="messages">
        <header>
            <authorName>Netresearch</authorName>
            <authorEmail>test@netresearch.de</authorEmail>
        </header>
        <body>
            <trans-unit id="component|type|placeholder">
                <target>Value</target>
            </trans-unit>
        </body>
    </file>
</xliff>
```
#### How to import 

* Open the TextDB Backend module
* Click on the import button 
* Choose a file to import which meet the criteria above 
* Click the import button

If you wish to replace existing entries click at the "overwrite existing" checkbox. 

### Export

It is also possible to Export a view of translations to import it in an other TYPO3 or to edit it an reimport it on 
the same instance. 

To be able to export open the TextDB backend module in the list view and choose a Component or Type. To start the export
click the "export with current filter" button below the list. This will export all entries with the current filtert
and for all languages. The export will not respect the pagination.  

## Migration ViewHelper

* In order to migrate LLL file templates to textDB entries the extension provides a ``textdb:translate`` viewhelper
* It implements the same interface like the ``f:translate`` viewhelper
* so to migrate your translation try the following steps

1. Include the textdb viewhelper to your template e.g. ``xmlns:textdb="http://typo3.org/ns/Netresearch/NrTextdb/ViewHelpers"``
2. replace ``f:translate`` calls in your template with ``textdb:translate`` calls
3. go to your controller and set the required component e.g. ``TranslateViewHelper::$component = 'my-component';``
4. call your templates/views etc. in frontend

* the ``textdb:translate`` viewhelper will load the current translation 0 as well as the language with uid 1
* it will load the translations from LLL files and insert them to the textDB
* After completed proceed as follows

1. reset your template and code changes
2. replace your ``f:translate`` calls with ``textdb:textdb`` calls by using the following regex with e.g. notepad++

````
// replace <f:translate stuff
search for   => <f:translate key="LLL:EXT:[^:]+:([^\"]+)\"[^>]+>
replace with => <textdb:textdb component="<yourcomponent>"  placeholder="\1"  type="label" />

// replace for {f:translate stuff
search for   => {f:translate\(key: 'LLL:EXT:[^:]+:([^\']+)'\)}
replace with => {textdb:textdb\({placeholder: '\1'\, component : '<yourcomponent>' , type : 'label'})}
````


## Testing & Development

- We use GrumPHP to comply with coding standards, perform unit testing, and ensure code coverage

```bash
vendor/bin/grumphp run
```

- In addition, we use Rector to automatically perform migrations, comply with coding standards,
  and simplify and improve the code.

```bash
vendor/bin/rector process [--dry-run]
```


## Known Issues

@TODO: Add Configuration Manual 

Nr TextDB
=========



Migration ViewHelper
====================
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

```
// replace <f:translate stuff
search for   => <f:translate key="LLL:EXT:[^:]+:([^\"]+)\"[^>]+>
replace with => <textdb:textdb component="<yourcomponent>"  placeholder="\1"  type="label" />

// replace for {f:translate stuff
search for   => {f:translate\(key: 'LLL:EXT:[^:]+:([^\']+)'\)}
replace with => {textdb:textdb\({placeholder: '\1'\, component : '<yourcomponent>' , type : 'label'})}
```

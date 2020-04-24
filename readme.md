Element Helper for MODx Revolution
==================================

Element Helper is a MODx plugin for automatically creating elements from static files without the need for the manager.

Installation
------------

Do not install or update through the MODx package manager. This is a customised Extra for Aspire Creative's own purposes.


Usage
-----

* Syncing of MODX's or other Extra's elements should be avoided as this can result in elements contents being erased. When adding custom elements to use with an Extra keep them in their own category e.g. FormIt chunks should be added to `Chunks > Forms` not `Chunks > Formit`
* Avoid using duplicate category/folder names. This breaks when generating elements. For example, `Forms > Tpls` and `Emails > Tpls` would likely pull the Tpl subcategories out into a top level category. Instead use `Forms > FormTpls` and `Emails > EmailTpls`
* **Important** Make sure that MODXs own static elements is turned off before running - `core > static elements` 

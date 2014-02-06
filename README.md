# H&O Importer

A Magento module and extension of the [AvS_FastSimpleImport][] module which allows you to map fields and import all sorts of file formats, data sources and data entities.

The module consists of various downloaders (http), source adapters (csv, spreadsheets, database or xml) and supports all entities that [AvS_FastSimpleImport][] supports (products, categories, customers) and last but not least allows you to field map all fields in from each format to the magento format.

All this configuration can be done using XML. You add the config to a config.xml and you can run the profile. The idea is that you set all the configuration in the XML and that you or the cron will run it with the perfect options.

Since the original target for the module was an import that could process thousands of products it is build with this in mind. It is able to process large CSV or XML files while using very little memory (think a few MB memory increase for processing a 1GB CSV file). See [Use cases](#use-cases)

We have chosen to do all configuration in XML, this makes the import profile way more maintainable, especially important when doing multiple imports for a single project.

To increase development and debugging speed there is a extensive shell tool that allows you to easily create new fieldmaps, add a downloader and start working.

![Terminal Preview](docs/images/terminal.png)

Example config for a customer import (this is added to the `<config><global><ho_import>` node:

```XML
<my_customer_import>
    <entity_type>customer</entity_type>
    <downloader model="ho_import/downloader_http">
        <url>http://google.nl/file.xml</url>
    </downloader>
    <source model="ho_import/source_adapter_xml">
        <file>var/import/Klant.xml</file>
        <!--<rootNode>FMPDSORESULT</rootNode>-->
    </source>
    <import_options>
        <!--<continue_after_errors>1</continue_after_errors>-->
        <!--<ignore_duplicates>1</ignore_duplicates>-->
    </import_options>
    <events>
        <!--<source_row_fieldmap_before helper="ho_importinktweb/product::prepareRowCategory"/>-->
        <!--<import_before/>-->
        <!--<import_after/>-->
    </events>
    <fieldmap>
        <email field="Email"/>
        <_website helper="ho_importjanselijn/import_customer::getWebsites"/>
        <group_id helper="ho_import/import::getFieldMap">
            <field field="Status"/>
            <mapping>
                <particulier from="Particulier" to="1"/>
                <zakelijk from="Zakelijk" to="2"/>
            </mapping>
        </group_id>
        <prefix field="Voorletters"/>
        <firstname field="Voornaam" defaultvalue="ONBEKEND"/>
        <middlename field="Tussenvoegsel" />
        <lastname field="Achternaam" required="1"/>
        <company field="Bedrijfsnaam"/>
        <created_in helper="ho_importjanselijn/import_customer::getCreatedIn"/>
        <taxvat field="BTWnummer" />
        <password field="cWachtWoord" />
        <gender helper="ho_import/import::getFieldMap">
            <field field="Geslacht"/>
            <mapping>
                <male from="M" to="male"/>
                <female from="V" to="female"/>
                <male_female from="M+V" to="male+female"/>
            </mapping>
        </gender>
    </fieldmap>
</my_customer_import>
```
## Installation

You can install the module via modman:
```bash
modman clone git@github.com:ho-nl/Ho_Import.git
```

Or you can [download the latest release](https://github.com/ho-nl/Ho_Import/archive/master.zip) it and place it in you Magento root.


## Getting started

### 1. Create a module
The idea is that you create a very light weight module for each project or import. This module has
all the config for that specific import.

_Need help creating an empty module for your installation, use a [module creator](http://www.silksoftware.com/magento-module-creator/).

Example config:

```XML
<config>
	<modules>
		<Ho_ImportJanselijn>
			<version>0.1.0</version>
		</Ho_ImportJanselijn>
	</modules>
	<global>
		<helpers>
            <ho_importjanselijn>
                <class>Ho_ImportJanselijn_Helper</class>
            </ho_importjanselijn>
        </helpers>

        <!-- ... -->

        <ho_import>
            <profile_name>
				<entity_type>customer</entity_type>
				<!-- ... the rest of the config -->
            </profile_name>
        </ho_import>
    </global>
</config>
```

### 2. Add the default config
_This section assumes that you place these config values in `<config><global><ho_import><my_import_name>`_

Add something like the following to your profile (see chapters below for detailed configuration):
```XML
<entity_type>customer</entity_type>
<downloader model="ho_import/downloader_http">
    <url>http://google.nl/file.xml</url>
</downloader>
<source model="ho_import/source_adapter_xml">
    <file>var/import/Klant.xml</file>
    <!--<rootNode>FMPDSORESULT</rootNode>-->
</source>
<import_options>
    <!--<continue_after_errors>1</continue_after_errors>-->
    <!--<ignore_duplicates>1</ignore_duplicates>-->
    <partial_indexing>1</partial_indexing>
</import_options>
```

### 3. Run the line shell utility
_Make sure you have cache disabled, because all XML is cached in Magento_

```bash
php hoimport.php -action line -profile profile_name
```

You'll see something like:
![Terminal Preview](docs/images/firstrun.png)

The first table shows the first line from the source file and the second table shows the results how they would be imported into Magento. It shows the error on each line where they are represented.

### 4. Expand all the fieldmaps to your liking
Grab an example that is most to your liking from the docs/imports folder and copy those fields to your config.

Now continue to map all your fields until you are satisfied.

### 5. Run the actual import
You can now import the complete set.

```bash
php hoimport.php -action import -profile profile_name -dryrun 1
```

*To just test if the import would run, add `-dryrun 1` to the command*

You will probably run into errors the first try. When the importer runs into errors it will return the faulty row. It will return the row that is imported (unfortunately it won't return the source row since that row isn't know at this point of the import).

If a specific sku, for example, is giving you trouble, you can run the line utility and do a search.

```bash
php hoimport.php -action line -profile profile_name -search sku=abd
```

### 6. Schedule an import (cronjob)
If you are satisfied with the import you can [add a schedule to it](#cron-schedule), this will add it to the cron
scheduler and run it at your configured time:

![Terminal Preview](docs/images/schedule.png)

As you can see, we have a `ho_import_schedule` cron which add the imports to the the cron and cleans up the cron if imports are removed/renamed. To speed up this process, you can run it manually.

## Config documentation
_This section assumes that you place these config values in `<config><global><ho_import><my_import_name>`_

### Supported Entity Types
All the entities of the [AvS_FastSimpleImport][] are supported:

- `catalog_product`
- `customer`
- `catalog_category`
- `catalog_category_product`

Example Config:
```XML
<entity_type>customer</entity_type>
```

### Cron schedule
Use the same formatting as the default cron setup.

Using a cron expression:
```XML
<schedule><cron_expr>0 2 * * *</cron_expr></schedule>
```

Using a config path:
```XML
<schedule><config_path>configuration/path/cron_expr</config_path></schedule>
```


### Downloaders
The only current supported downloader is HTTP. New downloaders can be easily created.

#### HTTP Example (:white_check_mark: Low Memory)
```XML
<downloader model="ho_import/downloader_http">
    <url>http://google.nl/file.xml</url>

    <!-- the downloader defaults to var/import -->
    <!--<target>custom/download/path/filename.xml</target>-->
</downloader>
```


#### Temporarily disable a download:
```XML
<import_options>
	<skip_download>1</skip_download>
</import_options>
```

### Sources
A source is a source reader. The source allows us to read data from a certain source. This could be
a file or it even could be a database.


#### CSV Source (:white_check_mark: Low Memory)
The CSV source is an implementation of PHP's [fgetcsv](http://php.net/manual/en/function.fgetcsv.php)


```XML
<source model="ho_import/source_adapter_csv">
    <file>var/import/customer.csv</file>

    <!-- the delimmiter and enclosure aren't required -->
    <!--<delimiter>;</delimiter>-->
    <!--<enclosure></enclosure>-->
</source>
```


#### XML Source (:white_check_mark: Low Memory)
The XML source is loosely based on [XmlStreamer](https://github.com/prewk/XmlStreamer/blob/master/XmlStreamer.php).

```XML
<source model="ho_import/source_adapter_xml">
    <file>var/import/products.xml</file>

    <!-- If there is only one type of entity in the XML the custom rootnode isn't required -->
    <!-- <rootNode>customRootNode</rootNode> -->
</source>
```


#### Spreadsheet Source (:white_check_mark: Low Memory)
The Spreadsheet Source is an implementation of [spreadsheet-reader](https://github.com/nuovo/spreadsheet-reader) and therefor supports

> So far XLSX, ODS and text/CSV file parsing should be memory-efficient. XLS file parsing is done with php-excel-reader from http://code.google.com/p/php-excel-reader/ which, sadly, has memory issues with bigger spreadsheets, as it reads the data all at once and keeps it all in memory.

```XML
<source model="ho_import/source_adapter_spreadsheet">
    <file>var/import/products.xml</file>

    <!-- If the first line has headers you can use that one, else the columns will only be numbered -->
    <!-- <has_headers>1</has_headers> -->
</source>
```


#### Database Source
The Database source is an implementation of `Zend_Db_Table_Rowset` and allows all implentation of `Zend_Db_Adapter_Abstract` as a source. It supports MSSQL, MySQL, PostgreSQL, SQLite and many others. For all possible supported databases take a look in `/lib/Zend/Db/Adapter`.

The current implementation isn't low memory because it executes the query and loads everything in memory.

```XML
<source model="ho_import/source_adapter_db">
    <host><![CDATA[hostname]]></host>
    <username><![CDATA[username]]></username>
    <password><![CDATA[password]]></password>
    <dbname><![CDATA[database]]></dbname>
    <model><![CDATA[Zend_Db_Adapter_Pdo_YourFavoriteDatabase]]></model>
    <pdoType>dblib</pdoType>
    <query><![CDATA[SELECT * FROM Customer]]></query>
    <!--<limit>10</limit>-->
    <!--<offset>10</offset>-->
</source>
```

If your PDO driver doesn't support `pdoType` then simply remove that node. If you wish to pass more config parameters to the PDO driver then add more nodes like for PGSQL: `<sslmode>require</sslmode>`

### Import Options

All the options that are possible with the [AvS_FastSimpleImport][] are possible here as well:

```XML
<import_options>
	<error_limit>10000</error_limit>
    <continue_after_errors>1</continue_after_errors>
    <ignore_duplicates>1</ignore_duplicates>
    <allow_rename_files>0</allow_rename_files>
    <partial_indexing>1</partial_indexing>
    <dropdown_attributes>
        <country>country</country>
    </dropdown_attributes>
    <multiselect_attributes>
        <show_in_collection>show_in_collection</show_in_collection>
        <condition>condition</condition>
        <condition_label>condition_label</condition_label>
    </multiselect_attributes>
</import_options>
```

### Events
All events work with a transport object which holds the data for that line. This a `Varien_Object`
with the information set.

```XML
<events>
	<process_before helper="ho_import/import_product::prepareSomeData"/>
	<import_before helper="ho_import/import_product::callWifeIfItIsOk"/>
	<source_row_fieldmap_before helper="ho_import/import_product::checkIfValid"/>
	<import_after helper="ho_import/import_product::reindexStuff"/>
	<process_after helper="ho_import/import_product::cleanupSomeData"/>
</events>
```

#### Event: `import_before`
- `object`: instance of `AvS_FastSimpleImport_Model_Import`

#### Event: `source_row_fieldmap_before`
It has one field `items` set. This can be replaced, extended etc. to manipulate the data. Optionally
you can set the key `skip` to `1` to skip this source row all together.

#### Event: `import_after`
- `object`: instance of `AvS_FastSimpleImport_Model_Import`
- `errors`: array of errors



### Fieldmap
This is where the core of the module happens. Map a random source formatting to the Magento format.

The idea is that you specify the Magento format here and load the right values for each Magento
field, manipulate the data, etc. There is a syntax to handle the most easy cases and have the
ability to call an helper if that isn't enough.

_This section assumes that you place these config values in `<config><global><ho_import><my_import_name><fieldmap>`_

#### Value
```XML
<tax_class_id value="2"/>
```

#### Field
```XML
<email field="Email"/>
```

In multi-level files like XML you can get a deeper value with a `/`

```XML
<email field="Customer/Email"/>
```

If there are attributes available, you can reach them with `@attributes`.

```XML
<sku field="@attributes/RECORDID"/>
```

#### Helper
Have the ability to call a helper method that generates the value. The contents of the field are the
arguments passed to the helper.

```XML
<_website helper="ho_import/import::getAllWebsites"><limit>1</limit></_website>
```

Calls the method in the class `Ho_Import_Helper_Import` with the first argument being the line and
the rest of the arguments being the contents in the node, in this case the limit.

```PHP
/**
 * Import the product to all websites, this will return all the websites.
 * @param array $line
 * @param $limit
 * @return array|null
 */
public function getAllWebsites($line, $limit) {
    if ($this->_websiteIds === null) {
        $this->_websiteIds = array();
        foreach (Mage::app()->getWebsites() as $website) {
            /** @var $website Mage_Core_Model_Website */

            $this->_websiteIds[] = $website->getCode();
        }
    }

    if ($limit) {
        return array_slice($this->_websiteIds, 0, $limit);
    }

    return $this->_websiteIds;
}
```

For more available helpers please see [Integrated helper methods](#integrated-helpers) and [Custom helper methods](#custom-helpers)

#### Use
Sometimes you want the same value multiple times in multiple fields. This loads the config of the
other fields and returns the result of that.

```XML
<image_label use="name"/>
```

#### Default value
```XML
<firstname field="First_Name" defaultvalue="UNKNOWN"/>
```

#### If field value
```XML
<company iffieldvalue="Is_Company" field="Company_Name"/>
```

#### Unless field value
The opposite of `iffieldvalue`

```XML
<firstname unlessfieldvalue="Is_Company" field="Customer_Name"/>
```

#### Required
Some fields are always required by the importer for each row. For example for products it is required that you
have the sku field always present.

```XML
<sku field="sku" required="1"/>
```

### Setting store view specific data
With simple additions to the config it is possible to set store view specific data. You have the exact same abilities as with normal fields, you only have to provide the `<store_view>` element with the fields for each storeview.

```XML
<description field="description_en">
    <store_view>
        <pb_de field="description_de"/>
        <pb_es field="description_es"/>
        <pb_fr field="description_fr"/>
        <pb_it field="description_it"/>
        <pb_nl field="description_nl"/>
    </store_view>
</description>
```

### Integrated helper methods <a name="integrated-helpers"></a>
There are a few helper methods already defined which allows you to do some common manipulation
without having to write your own helpers

#### getAllWebsites
```XML
<_website helper="ho_import/import::getAllWebsites">
	<limit>1</limit> <!-- optional -->
</_website>
```

#### findReplace
```XML
<short_description helper="ho_import/import::findReplace">
	<value field="sourceField"/>
    <findReplace>
        <doubleat find="@@" replace="@"/>
        <nbsp from="&nbsp;" replace=" "/>
    </findReplace>
    <trim>1</trim> <!-- optional -->
</short_description>
```

#### parsePrice
```XML
<price helper="ho_import/import::parsePrice">
    <pricefield field="PrijsVerkoop"/>
</price>
```

#### formatField
Implementation of [vsprinf](http://us1.php.net/vsprintf)

```XML
<meta_description helper="ho_import/import::formatField">
    <format>%s - For only €%s at Shop.com</format>
    <fields>
        <description field="Info"/>
        <price field="PrijsVerkoop"/>
    </fields>
</meta_description>
```

#### truncate
```XML
<description helper="ho_import/import::truncate">
    <value field="Info"/>
    <length>125</length>
    <etc>…</etc>
</description>
```

#### stripHtmlTags
```XML
<description helper="ho_import/import::stripHtmlTags">
    <value field="A_Xtratxt"/>
    <allowed><![CDATA[<p><a><br>]]></allowed>
</description>
```

#### getHtmlComment
Get a simple HTML comment (can't be added through XML due to XML limitations).

```XML
<description helper="ho_import/import::getHtmlComment">empty</description>
```

#### getFieldBoolean
```XML
<is_in_stock helper="ho_import/import::getFieldBoolean">
	<value field="stock"/>
</is_in_stock>
```

#### getFieldMultiple
Allow you to load multiple fields. Each field has the same abilities as a normal field (allows you
to call a helper, value, field, iffieldvalue, etc.

```XML
<_address_prefix helper="ho_import/import::getFieldMultiple">
    <fields>
        <billing iffieldvalue="FactAdres" field="Voorvoegsel"/>
        <shipping iffieldvalue="BezAdres" field="Voorvoegsel"/>
    </fields>
</_address_prefix>
```

#### getFieldCombine
Get multiple fields and glue them together

```XML
<sku helper="ho_import/import::getFieldCombine">
    <fields>
        <prefix value="B"/>
        <number field="BmNummer"/>
    </fields>
    <glue>-</glue> <!-- optional, defaults to a space -->
</sku>
```

#### getFieldSplit
Split a field into multiple pieces

```XML
<_category helper="ho_import/import::getFieldSplit">
    <field field="category"/>
    <split>***</split>
</_category>
```

#### getFieldMap
```XML
<gender helper="ho_import/import::getFieldMap">
    <value field="Geslacht"/>
    <mapping>
        <male from="M" to="male"/>
        <female from="V" to="female"/>
    </mapping>
</gender>
```

#### getFieldCounter
```XML
<_media_position helper="ho_import/import::getFieldCounter">
    <countfield field="cImagePad"/>
</_media_position>
```

#### ifFieldsValue
You can normally define `iffieldvalue='fieldname'` to do simple value checking. Something you need
to check multiple fields.

```XML
<billing_first_name helper="ho_postbeeldproduct/import_customer::ifFieldsValue">
    <fields>
        <billing_first_name field="billing_first_name"/>
        <billing_last_name field="billing_last_name"/>
        <billing_address field="billing_address"/>
        <billing_city field="billing_city"/>
        <billing_country_code field="billing_country_code"/>
    </fields>
    <billing field="billing_first_name"/>
</billing_first_name>
```

#### getMediaAttributeId
Usually used in combination with a counter to set the correct getMediaAttributeId

```XML
<_media_attribute_id helper="ho_import/import::getFieldCounter">
    <countfield field="cImagePad"/>
    <fieldvalue helper="ho_import/import::getMediaAttributeId"/>
</_media_attribute_id>
```

#### getMediaImage
Download the image from a remote URL and place it in the `media/import` folder.

```XML
<image helper="ho_import/import::getMediaImage">
    <imagefield field="cImagePad"/>
    <limit>1</limit>
</image>
```


#### timestampToDate
Parse a timestamp and output in the Magento running format, just specify in which timezone the  current date is. Add an offset with one of the [Relative Formats](http://am1.php.net/manual/en/datetime.formats.relative.php).

```XML
<news_to_date helper="ho_import/import::timestampToDate">
    <field field="entry_date"/>
    <timezoneFrom>Europe/Amsterdam</timezoneFrom>
    <offset>3 day</offset>
</news_to_date>
```

#### Product: getUrlKey
```XML
<url_key helper="ho_import/import_product::getUrlKey">
    <fields>
        <name field="Titel"/>
    </fields>
    <glue>-</glue>
</url_key>
```

#### Category: getUrlKey
```XML
<url_key helper="ho_import/import_category::getUrlKey">
    <fields>
        <name field="Titel"/>
    </fields>
    <glue>-</glue>
</url_key>
```

#### Customer: mapCountryIso3ToIso2
```XML
<billing helper="ho_import/import_customer::mapCountryIso3ToIso2">
    <field field="billing_country_code"/>
</billing>
```

#### Customer: mapCountryIso2ToIso3
```XML
<billing helper="ho_import/import_customer::mapCountryIso2ToIso3">
    <field field="billing_country_code"/>
</billing>
```

### Custom helper methods <a name="custom-helpers"></a>
Not every situation is a simple value processing and more complex logic might have to be used. You have the ability to easily create your own helper methods for each project. Simply create your own helper class and call that class.

Example: To determine if an address is a default address we create the two fields:

```XML
<_address_default_billing_  helper="ho_importjanselijn/import_customer::getAddressDefaultBilling"/>
<_address_default_shipping_ helper="ho_importjanselijn/import_customer::getAddressDefaultShipping"/>
```

And create a helper class which with the methods:

```PHP
class Ho_ImportJanselijn_Helper_Import_Customer extends Mage_Core_Helper_Abstract
{

    public function getAddressDefaultBilling($line) {
        if ($line['InvAddress']) { //there is a billing and shipping address
            return array(1,0);
        } else { //there is only a shipping address
            return 1;
        }
    }

    public function getAddressDefaultShipping($line) {
        if ($line['InvAddress']) { //there is a billing and shipping address
            return array(0,1);
        } else { //there is only a shipping address
            return 1;
        }
    }
}
```

As you can see it sometimes returns an array of values and sometimes just returns a value. If you helper method returns an array of values Ho_Imports [internally rewrites those multiple values to multiple import rows](https://github.com/ho-nl/Ho_Import/blob/master/app/code/local/Ho/Import/Model/Import.php#L470).

## CLI / Shell Utility
The importer comes with a shell utiliy where you'll be spending most of your time.

### line
```
php hoimport.php  -action line
	-profile profile_name             Available profiles:    janselijn_customers
	-line 1,2,3                       Comma separated list of lines to be checked
	-search sku=abd                   Alternatively you can search for a value of a field
```

### import
```
php hoimport.php -action import
	-profile profile_name             Available profiles:    janselijn_customers
	-partial_indexing 1               When done importing will the imported products be indexed or will the whole system be indexed
	-continue_after_errors 1          If encountered an error, will we continue, sometimes one row is corrupt, but the rest is fine
	-dropdown_attributes attr1,attr2  Comma separated list of dropdownattributes that are autofilled when importing.
	-rename_files 0                   Normally, when importing, images are renamed if an image exists. Set this to 0 to overwrite images
	-dryrun 1                         Run a dryrun, validate all data agains the Magento validator but do not import anything
	-ignore_duplicates 1              Ignore duplicates.;
	-error_limit 10000                Set the error limit, default=100 error lines.;
```

## Logging
There are two logging modes: CLI and cron mode. In the CLI mode it always logs to the CLI and tries
to add nice colors, etc. In the cron-mode it will log to the the log files and can also log to the
messages inbox in the admin panel.

### File logging
Every import run by the cron is saved in `var/ho_import.log`.

### Admin Panel notification


Sometimes you want to put a message in the Admin panel if an error pops up. By default the system
only creates an admin panel message if there is a warning.

```PHP
EMERG   = 0;  // Emergency: system is unusable
ALERT   = 1;  // Alert: action must be taken immediately
CRIT    = 2;  // Critical: critical conditions
ERR     = 3;  // Error: error conditions
WARN    = 4;  // Warning: warning conditions
NOTICE  = 5;  // Notice: normal but significant condition
INFO    = 6;  // Informational: informational messages
DEBUG   = 7;  // Debug: debug messages
SUCCESS = 8;  // Success: When everything is going well.
```

Place these config values in `<config><global><ho_import><my_import_name>` to change the level when
and admin panel message will be added.

```XML
<log_level>6</log_level>
```


## Use cases
At the time of release we have this tool running for multiple clients, multiple types of imports:
- One time product / category imports from an old datasource [Example config](docs/imports/old_products.xml)
- Periodic category import with values for multiple store views [Example config](docs/imports/categories_with_store_view_data.xml)
- 15 minute inventory only updates
- Nightly complete inventory updates [Example config](docs/imports/product_stock_multiple.xml)
- Nightly price updates
- Incremental category/product updates from ERP systems
- Customer import [Example config](docs/imports/customer_import.xml)
- Customer import with billing and shipping address [Example config](docs/imports/customer_import_billing_shipping.xml)

## Performance
We don't have actual benchmarks at the moment, but the time spend fieldmapping is an order of magnitude faster than the actual import its self.

## License
[OSL - Open Software Licence 3.0](http://opensource.org/licenses/osl-3.0.php)

## Support
If you need help with the module, create an issue in the [GitHub issue tracker](https://github.com/ho-nl/Ho_Import/issues).

## Author
The module is written by Paul Hachmang (twitter: [@paales](https://twitter.com/paales), email: paul@h-o.nl) build for H&O (website: <http://www.h-o.nl/>, email: <info@h-o.nl>, twitter: [@ho_nl](https://twitter.com/ho_nl)).

## Why build it and open source it?
After having build multiple product, category and customer imports I was never really satisfied with the available projects. After implementing a project with bare code we came to the conclusion that it was pretty difficult to create an import, make sure al the fields are correctly set for Magento to accept them, the development iteration was to slow, etc.

After building this we think we made a pretty good module that has value for a lot of Magento developers, so releasing it open source was natural. And with the combined effort of other developers, we can improve it even further, fix bugs, add new features etc.

[AvS_FastSimpleImport]: https://github.com/avstudnitz/AvS_FastSimpleImport "AvS_FastSimpleImport by @avstudnitz"

# PHP XMLBeans Samples

These samples are PHP ports of the [Apache XMLBeans samples](https://xmlbeans.apache.org/samples/index.html), adapted for the PHP XMLBeans API.

Each sample is a standalone PHP script. Run from the project root:

```bash
php samples/<SampleName>.php
```

## Samples

| Sample | Description |
|---|---|
| [DateTime](DateTime.php) | Working with XSD date, time, dateTime, and duration types |
| [Validation](Validation.php) | Validating XML instances against a schema |
| [XPathQuery](XPathQuery.php) | Running XPath expressions with the builder, engine, and cursors |
| [CursorNavigation](CursorNavigation.php) | Navigating and editing XML with XmlCursor |
| [MixedContent](MixedContent.php) | Handling mixed-content elements using cursors |
| [OrderMatters](OrderMatters.php) | How document order affects processing — typed vs cursor access |
| [SchemaEnum](SchemaEnum.php) | Working with schema enumerations |
| [HandlingAny](HandlingAny.php) | Accessing xs:any wildcard content via cursors and XPath |
| [BuiltinTypes](BuiltinTypes.php) | Using the 46 XSD built-in type wrappers |
| [XmlTree](XmlTree.php) | Building a tree representation of an XML document |
| [GettingStarted](GettingStarted.php) | End-to-end tutorial: parse, query, compile, generate, validate |

## Sample XML and XSD Files

Sample data files are in the `data/` subdirectory.

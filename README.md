# Virginia Decoded

The Virginia implementation of [The State Decoded](https://github.com/statedecoded/statedecoded/), found at [vacode.org](https://vacode.org/).

## Testing XSLT

XML transformations can be applied like such:

```
xsltproc ../vacode-dev.xslt sample_1.xml
```

...except that xsltproc doesn't support XSLT 2.0. I'm going to have to figure out an alternative. The goal is to get the outputted XML to match [the State Decoded standard](http://docs.statedecoded.com/xml-format.html).

Note that this XSLT is particularly important because it is LexisNexis’ XML format for legal codes. Getting this created means that any LexisNexis legal XML can be used to populate a State Decoded website.

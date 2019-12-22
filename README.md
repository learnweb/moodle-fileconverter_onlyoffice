# ONLYOFFICE Document Converter #

This is a tool that enables Moodle to use an ONLYOFFICE server for converting documents. For example, this is useful in assignment submissions. In combination with an ONLYOFFICE server, submitted text documents, spreadsheets, and presentations are automatically converted to PDF to simplify the grading workflow.

The plugin leverages the [Conversion API](https://api.onlyoffice.com/editors/conversionapi) offered by the [ONLYOFFICE Document Server](https://github.com/ONLYOFFICE/DocumentServer), which is free software. 

![EKYkLibXUAEzQHW](https://user-images.githubusercontent.com/432117/71324920-6c38e480-24e5-11ea-860d-822149e88cd0.png)

## Setup

First, make sure that you have the ONLYOFFICE document server installed and determine its URL *as seen from the local network!*.
For the following steps, let's assume it is located at https://documentserver.onlyoffice.local/ (replace with the actual URL!).
Also, find out the URL at which you reach Moodle (*locally*). For now, let's assume it is at https://moodle.local/ (replace that, too).

Note that the URLs are required to be valid for server-to-server communication. Later, none of these URLs will ever be entered into the browser. They are only(!) required to make Moodle talk to the Document Server, and vice versa. **In most setups, there is no difference and you can just use the normal URLs from the browser!** But under some circumstances, e.g. when using a Docker container setup, you need to use the internal URLs.   

1.  Copy the plugin into `<MOODLE DIR>/files/converter/onlyoffice`.
2.  Start the installation from the Moodle web interface.
3.  Fill in the two settings that you are presented with:

| Setting name          | Value          |
|-----------------------|----------------|
| internaloodsurl | https://documentserver.onlyoffice.local/ |
| internalmoodleurl | https://moodle.local/ | 

The plugin is successfully installed. Now, to test the converter, create a mod_assign that allows submission of files with any of these formats:
* Document formats:
              'doc', 'docx', 'rtf', 'odt', 'html', 'txt',
* Spreadsheet file formats:
              'xls', 'xlsx', 'ods', 'csv',
* Presentation file formats:
              'ppt', 'pptx', 'odp'.

As a student, create a submission uploading an appropriate file.
Then, as a teacher, go to grading and look at the online grading interface.
It might take a while until the document is converted.
Afterwards, it will show up on the left-hand side.
It should look similar to the screenshot at the top of this file.
    
## Troubleshooting

* Look into the logs of the Moodle and ONLYOFFICE servers, verifying that 
   1. requests show up: Before conversion, there should be at least one request from Moodle to ONLYOFFICE (asking for conversion), and one request from ONLYOFFICE to Moodle (accessing the file).
   2. there are no error messages
* If fewer requests are logged than expected, make sure that the URLs that you specify are both correct and reachable from the servers' internal network. For example, log on to the servers and try to fetch the URLs via CURL. Observe the error messages.

If there is a problem that you cannot deal with, please file an issue on GitHub.

djml-parser
===========

DJML parser for improved error reporting

To run the script, pull the scripts to local machine. Assuming that php is installed on local machine, run the following command from the appropriate file directory (in terminal/cmd): 

    php DJMLParser.php ./bad_djml_samples/sample_djml

**Note** : Change directory name to point to the djml file to be tested.


Validation Error types 
---

Validation errors have been classified into 5 major types based on the structure of the error messages displayed from libxml validation. 

1. Element 'element_name' does not carry attribute attribute_name

 The error line numbers corresponding to this error type are pointed out by the inbuilt libxml validation function used in the script.

2. Entity loading errors:
    1. Failed to load external entity 
    2. Could not load the external subset

 This error is assumed to occur due to the entity/subset link(s) being down. Seems to resolve automatically after some time.

3. Element p is not declared in p list of possible children (aka. Nested p tag error)

4. The expected-got errors:
    1. Element list content does not follow the DTD, expecting (...), got (...)
    2. Element article-body content does not follow the DTD, expecting (...), got (...)
    3. Element alt-summary-body content does not follow the DTD, expecting (...)*, got(…)
    
5. Element 'X' is not defined in 'Y' list of possible children. 


Parser Structure
---

The DJML parser aims to add an additional layer of error interpretting on top of the out of the box libxml function's error reporting. Certain error types result in accurate error line numbers by the inbuilt validation function (as mentioned earlier). However, for error types 3 and 4 - the error line numbers do not suffice do not pinpoint the error lines precisely. 

2 functions were created for accurate error line pinpointing (getting the exact error line and displaying it). 

1. errorInterpret() :-

 The error interpret function uses the libxml generated error messages to classify what type of error has occurred. This is done by a string comparison function with the error messages (looking for specific phrases). Based on the error type, further processing is done as needed. In the case of type 3 errors, getArticleLine() is called directly. Type 4 errors required significant processing before calling getArticleLine(). 

  ###Type 4 error interpreter logic:
  Calling getArticleLine() directly on the error line for a type 4 error results in just the printing of the opening line of article-body (which does not necessarily have the error at all). Referring to the type 4 error types, the script pulls out the 'got(...)' part of the error message. Following some processing, this is used to create an array of input tags. Each of these input tags is then compared with the permitted article body elements. On finding an illegal element, its location is determined along with the location of the last p tag element prior to it. This p tag location alongwith the flag type is passed to getArticleLine().

2. getArticleLine() :-
 This function is used to print out the line from the djml corresponding to the error line number (which was pinpointed earlier). It takes error line number, error type and root element as input parameters. 
 
 
 **Note**: By default, root of the document is set to p. This is because the underlying logic of the parser is that p tags are the most frequently present tags in any random document. If the root has not been explicitly passed from errorInterpret(), p tags can be used to traverse the document for error pinpointing in most cases. 
 
 
Depending on the error type, appropriate processing is done to access and display the correct lines from the djml. Broadly, there are 4 cases defined:

0. Case 0 deals with type 1 and 5 errors. ie. Errors where an element contains an undefined attribute, or an undefined element is nested within another element. It also deals with type 4(i) errors - expected-got errors on list element. 
1. Case 1 deals with type 3 errors - ie. nested p tag errors.
2. Case 2 deals with type 4(ii) errors - ie. article-body errors.
3. Case 3 deals with type 4(iii) errors - ie. alt-summary-body errors.

While interpretting the results of case 3, the displayed line is usually the line immediately **before** the problem text. 


Adapting script for new error cases
---

If a new type of error is encountered in the future, both functions of the script will need to be added to. Specifically:
- A new if case would have to be defined in the errorInterpretter(). Depending on the structure of the error message, and how the error is to be processed - existing values of $flag *can* be used. This should be done carefully though, and only after comparing with existing error message structures. 
- If there is any ambiguity in the error message structure being compared, creating a new value for flag and processing it seperately would be appropriate. Remember that document root is set to 'p' by default - meaning that unless specified otherwise, getArticleLine() will capture all the p tags in the DJML and do further processing on them (error line number comparisons, nested children, etc). Lines 134-146 of the script show one way of capturing and passing the dynamic value for root tag. 
- If existing values of flag are used in errorInterpretter() - very few (if any) additions would need to be made to getArticleLine(). If a new flag was defined and passed, an appropriate case would have to defined in getArticleLine() to get the appropriate error line. 


Setup prior to first time web service use
---
Before the web service is run for the first time, a few things need to be set up on the server:

1. Composer (dependency manager)
2. Slim framework
3. The .htaccess file

###Installing Composer
Composer is a tool for dependency management in PHP. 
To globally install Composer to the system:

    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
    
###Installing Slim framework

Install the Slim framework in the project directory.

    $ composer require slim/slim:~2.0

###Creating .htaccess file

This is the config file used for web servers running Apache. This file needs to be created prior to the first run (and is hidden unless an 'ls -a' command is run through terminal).
Create file named .htaccess in the same directory where Slim was installed. .htaccess contains the following:

    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [QSA,L]


Running DJML Parser as a Web Service
---
To run the script as a web service, the Slim framework was used. The script takes in POST requests with key:value pair as follows: 
"djml:[djml_to_test]"

For dev and testing purposes, the Postman extension of Chrome was used for sending POST requests to 'localhost/slimtest/parse' (where slimtest was the folder host with slim framework, DJMLParser.php, etc.)

**Note** : In deployment, this address should be modified to 'server_address/parser_folder/parse'


Future Scope
---

In the future, the script can be integrated into the existing DJML validation system so that the error reporting of this script could be displayed directly in the wordpress publishing panel. 
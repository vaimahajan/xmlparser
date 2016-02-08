<?php

// DJML Parser for DTD validation and improved error reporting
// Author : Vaibhav Mahajan


//for web form based validation
if (isset($_POST['djmlBlock']))
{
    $djml = $_POST['djmlBlock'] ; 
}

//for cli based validation
$sapi_type = php_sapi_name() ; 
if ($sapi_type === 'cli')
{
    if ($argc >= 2) 
    {
        $fullpath_of_input_file = $argv[1];

        $myfile = fopen($fullpath_of_input_file, "r") or die("Unable to open file!");
        #echo fread($myfile,filesize($fullpath_of_input_file));
        $djml = fread($myfile,filesize($fullpath_of_input_file));
        fclose($myfile);

        #echo $djml;
    }
}

$doc = new DOMDocument('1.0', 'ASCII');

//media, byline optional - this DTD applies to article-body and alt-summary-body!
$commonDTD = array("p", "media", "headline", "byline", "client-imagemap", "form", "ident", "inset", "list", "list-item", "media", "popup", "pre", "redein", "section", "signature", "small-hed", "subhed", "table", "tagline", "dateline", "blockquote", "temporary-info");

//disabling error reporting to avoid notices popping up when running script on apache
error_reporting(0);

class djmlparser
{
    public function __construct($djml)
    {
        global $djml; 
        global $doc ;
        libxml_use_internal_errors(true) ;
        
        $doc->loadXML($djml);
        
        //inbuilt libxml validation
        if ($doc->validate())
        {
            echo "Document validation successful! \n" ; 
        }
        else
        {
            $errors = libxml_get_errors();	
            echo "Validation error! \n" ;
            
            foreach ($errors as $error) 
            {
                echo $error->message . "in line " . $error->line . "\n" ;
                $this->errorInterpret($error->message, $error->line) ; 
            }
            libxml_clear_errors() ; 
        }    
    }

    public function errorInterpret($ermssg,$erline)
    {   
        global $commonDTD ; 
        
        if (strpos($ermssg, 'p is not declared') !== false)
        {
            $flag = 1 ; 
            echo $this->getArticleLine($erline, $flag) . "\n" ; 
        }
        
        elseif (strpos($ermssg, 'article-body content does not follow the DTD') !== false || strpos($ermssg, 'alt-summary-body content does not follow the DTD') !== false )
        {
            //setting flag values - 2 for article-body error and 3 for alt-summary-body error
            if (strpos($ermssg, 'Element article-body content does not follow the DTD') !== false)
                $flag = 2 ; 
            else 
                $flag = 3 ; 
            
            preg_match("/got \(.*\)/", $ermssg, $input);            //=> got (p p )
            
            //headline is required in article-body
            if ($flag == 2 & strpos($input[0], 'headline') == false)
            {
                echo "\nNo headline tag present in article-body!\n" ; 
                //return ;  
            }
            
            else
            {
                preg_match("/\((.*?)\)/", $input[0], $temp) ;
                //echo $temp[0] ;                       => (p p ) 
                
                //stripping brackets from $temp[0]
                $tempstr = substr($temp[0],1,strlen($temp[0])-2) ; 
                //echo $tempstr . "\n" ;                 // => p p or p p ) based on whether 'got' part of error mssg had space at end
                $inputTags = preg_split("/[\s]+/", $tempstr) ;      //gives an indexed array with all 'got' terms (from error mssg)
                
                $result = array_diff($inputTags, $commonDTD);
                $problemIndex = key($result) ;
                
                //get count of number of tags prior to badtag which are non-p tags; subtract this from $problemIndex and pass new value to getArticleLine()
                
                $ignoreTagCount = 0 ; 
                for($y=0 ; $y<$problemIndex ; $y++)
                {
                    if ($inputTags[$y] != 'p')
                    {
                        $ignoreTagCount++ ; 
                    }
                }
                
                //echo "ignore tag count=" . $ignoreTagCount . "\n" ; 
                
                //getting highest p tag index prior to badtag index
                for ($ctr = count($inputTags) ; $ctr > 0 ; $ctr--)
                {
                    if ($ctr < $problemIndex & $inputTags[$ctr] == 'p')
                        break;
                }
                
                /*echo "Problem index = " . $problemIndex . "\n" ;
                echo "Highest p tag index prior to bad tag = " . $ctr . "\n" ; */
                
                $highP = $problemIndex - $ignoreTagCount ; 
                
                $this->getArticleLine($highP, $flag) ;   
            }
        }
        else
        {
            $flag = 0 ;
            $splitmssg = explode(' ', $ermssg);
            
            //setting root element to pass to getArticleLine()
            if (strpos($ermssg, 'list of possible children') !== false)
            {
                $passedroot = $splitmssg[6] ; 
            }
            else
            {
                $passedroot = $splitmssg[1] ; 
            }
            
            $this->getArticleLine($erline, $flag, $splitmssg[1]) ;
        }
        return; 
    }
    
    public function getArticleLine($libxLine, $errorType="x", $root="p")
    {
        global $doc ;
        global $sapi_type ; 
        $parent = $doc->getElementsByTagName($root);       //parent is DOMnodelist type ; contains all $root tag elements from document
        echo '<br>'."\nError details follow:-\n".'<br>' ; 
        
        switch($errorType)
        {
            case 0:                                         //default case for error types 1 and 2
                echo "Refer error line number mentioned above for corrections" . "\n";
                foreach($parent as $x)
                {
                    if ($x->getLineNo() == $libxLine)
                    {
                        echo "\nProblem line(s):- \n" ;
                        if ($sapi_type != 'cli')
                            echo htmlspecialchars($doc->saveXML($x)); 
                        else
                            echo $doc->saveXML($x);
                        //echo $x->nodeValue ;
                        return ; 
                    }
                }
                break;
            case 1:                                         //for type 3 errors ie. nested p tag errors
                foreach($parent as $x)
                {
                    foreach ($x->getElementsByTagName("p") as $ptag)
                    {
                        if ($ptag->getLineNo() == $libxLine)
                        {
                            echo "\nNested p tag error in following line:- \n" ;
                            return $ptag->nodeValue ;
                            break ; 
                        }
                    }
                }
                break;
            case 2:                                         //for type 4 article-body errors
                echo "\nInvalid text is in or right after the following line: \n" ; 
                $ltdpCtr = 0 ;      //p counter
                foreach ($parent as $test)
                {
                    //limiting all the p tags from parent to only the p tags within article-body
                    if ($test->parentNode->nodeName == 'article-body')
                    {
                        $ltdpCtr++ ;
                        if ($ltdpCtr == $libxLine)
                        {
                            if ($sapi_type != 'cli')
                                echo htmlspecialchars($doc->saveXML($test)) ; 
                            else
                                echo $doc->saveXML($test) . "\n";
                            //echo $test->nodeValue . "\n" ; 
                            return ;
                        }
                    }
                }
                break ; 
            case 3:                                         //for type 4 alt-summary-body errors
                echo "\nInvalid text is in or right after the following line: \n" ; 
                $ltdpCtr = 0 ;      //p counter
                foreach ($parent as $test)
                {
                    //limiting all the p tags from parent to only the p tags within alt-summary-body
                    if ($test->parentNode->nodeName == 'alt-summary-body')
                    {
                        $ltdpCtr++ ;
                        if ($ltdpCtr == $libxLine)
                        { 
                            echo $test->nodeValue . "\n" ; 
                            return ;
                        }
                    }
                }
                break ;
            default:
                echo "\nError location could not be pinpointed!\n" ;
                break ;
        }
        return ; 
    }
}

$obj = new djmlparser($djml);

?>
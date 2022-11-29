<?
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$text = file_get_contents('https://www.gutenberg.org/files/98/98-0.txt');
$vader = file_get_contents('https://raw.githubusercontent.com/cjhutto/vaderSentiment/master/vaderSentiment/vader_lexicon.txt');

$vader .= "\nlight	1.0	0.7	[2, 3, 4, 4, 3, 2, 3, 3, 2, 3]"; // add a noun from Dickens' first paragraph which deserves inclusion

$text = str_replace("’", "'", $text);
$text = preg_replace("/\r\n\r\n/","<p>",$text);
$text = preg_replace("/\r\n/"," ",$text);
$lines = explode("<p>", $text);

array_splice($lines, 0, 22); // strip preamble and contents
array_splice($lines, -52); // strip endnotes

$goodtimes = "";
$badtimes = "";
$blandtimes = "";
$firstgood = "";
$firstbad = "";

foreach($lines as $line)
{
	$line = trim($line);
	if (preg_match("/^CHAPTER ([IVX]+). ([\w ]+)$/",$line,$matches))
	{
		$goodtimes .= "<h2>CHAPTER ".$matches[1].".<br/>".$matches[2]."</h2>\n";
		$badtimes .= "<h2>CHAPTER ".$matches[1].".<br/>".$matches[2]."</h2>\n";
		$blandtimes .= "<h2>CHAPTER ".$matches[1].".<br/>".$matches[2]."</h2>\n";
		$firstgood = " fl"; $firstbad = " fl";
	}
	elseif (preg_match("/^Book the ([\w ]+)--([\w ]+)$/",$line,$matches))
	{
		$goodtimes .= "<h2>Book the ".$matches[1]."&mdash;".$matches[2]."</h2>\n";
		$badtimes .= "<h2>Book the ".$matches[1]."&mdash;".$matches[2]."</h2>\n";
	}
	else
	{
		$line = preg_replace("/[\r\n]/","",$line);
		
		$score = 0; $formatted = "";
		[$score,$formatted] = score($line);

		// improve the ASCII markup
		$formatted = preg_replace("/--/","&mdash;",$formatted);
		$formatted = preg_replace("/\(_([^_]*)_\)/","<i>($1)</i>",$formatted);
		$formatted = preg_replace("/_([^_]*)_/","<i>$1</i>",$formatted);

		$score = floor($score*10)/10;
		
		if ($score>0)
		{ $goodtimes .= "<div class=\"bl$firstgood\">$formatted</div>\n"; $firstgood = ""; } //<span class=\"us\">(&uarr;&nbsp;$score)</span>
		else if ($score<0)
		{ $badtimes .= "<div class=\"bl$firstbad\">$formatted</div>\n"; $firstbad = ""; }
		else
		{ $blandtimes .= "<div class=\"bl\">$formatted</div>\n"; }
	}
}

?><html>
<head>
<title>A Tale of Two Sentiment-Analysed Cities</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Spectral&family=Spectral+SC&display=swap" rel="stylesheet"> 
<style>
body { margin: 5% 20%; background: #fbf5ef; color:#333; font-family: 'Spectral', serif; text-align: justify; }
.gw { color:#24a824; }
.bw { color:#e82525; }
.cv { font-family:monospace; }
.us { font-family: monospace; color: #090; font-size: 0.7em; background: #efe; padding: 2px 3px; border-radius: 8px; font-weight: bold; }
.ds { font-family: monospace; color: #900; font-size: 0.7em; background: #fee; padding: 2px 3px; border-radius: 8px; font-weight: bold; }
.bl { color:#000; text-indent: 2em; }
.fl { text-indent: 0em; }
h1 { text-align:center; font-size: 3.0em; margin: 1em 20%; font-family: 'Spectral SC', serif; }
h2 { text-align:center; }
hr { margin: 3em; }
</style>
</head>
<body>

<h1>A Tale of Two Sentiment-Analysed Cities</h1>
<div class="intro">This is a piece of text generated automatically for <a href="https://github.com/NaNoGenMo/2022/">NaNoGenMo 2022</a> through
<a href="https://github.com/kevandotorg/nanogenmo-2022">a script</a> by <a href="https://kevan.org">Kevan Davis</a>. It takes the original text of Charles Dickens' <i>A Tale of Two Cities</i> and
analyses each paragraph for positive and negative sentiment, dividing up the text into two smaller books - <a href="#best">the first</a> containing all
net-positive paragraphs, and <a href="#worst">the second</a> containing all the negatives.</div><br/>

<div>The script uses the VADER sentiment lexicon. (Hutto, C.J. & Gilbert, E.E. (2014). <i>VADER: A Parsimonious Rule-based Model for Sentiment Analysis of Social Media Text. Eighth International Conference on Weblogs and Social Media (ICWSM-14).</i> Ann Arbor, MI, June 2014.)</div>
<hr>
<h1 id="best">The Best of Times</h1>

<?=$goodtimes?>

<hr>
<h1 id="worst">The Worst of Times</h1>

<?=$badtimes?>
<hr>

</body>
</html><?

function score($line)
{
	global $vader;
	
	$words = preg_split('/([^A-Za-z\'\-]+)/', $line, -1, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
	$score = 0; $notmul = 1; $posmul=1;
	$formatted = "";
	
	foreach($words as $oword)
	{
		$word = strtolower($oword);
		
		if ($word != "i'll") // shouldn't be parsed as "ill"
		{ $word = preg_replace("/[^A-Za-z]/","",$word); }
		$caps = 0;
		
		if ($word == "miss") // ignore this word, used almost entirely to address female characters
		{  }
		elseif ($word == "not" || $word == "no" || $word == "less") // ignore this word and invert the next nearby future word score
		{ $notmul = -1; }
		else
		{
			if (preg_match("/^[A-Za-z]+$/",$word))
			{
				if (preg_match("/^".$word."\t([0-9\.\-]+)\t/im",$vader,$matches))
				{
					$score += $matches[1]*$notmul*$posmul;
					$posmul += 0.001;
					$caps=$matches[1]*$notmul;
				}
				if (strlen($word)>2) { $notmul = 1; }
			}
		}
		
		
		if ($caps > 0)
		{ $formatted .= "<span class=\"gw\">$oword</span>"; }
		elseif ($caps < 0)
		{ $formatted .= "<span class=\"bw\">$oword</span>"; } 
		else
		{ $formatted .= "$oword"; }
	}
	
	$formatted = preg_replace("/(no|not|less) (\w\w? )*(<span class=\"[bg]w\">)/i","$3$1 $2",$formatted);

	return [$score,$formatted];
}

?>

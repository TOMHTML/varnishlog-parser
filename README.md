# Varnishlog Parser

A stand-alone tool to transform a varnishlog file into a nice diagram sequence.

![Screenshot](images/example_output.png)

## Requirements

 * Varnish 4.1
 * PHP 5 (tested with PHP 5.6)
 * Kink library (loaded as Git submodule)
 * Internet access to websequencediagrams.com (generated diagram)

## Installation and usage

First, you need to **generate a varnishlog output**.

 * Log in your Varnish server
 * Execute this command: `varnishlog -g raw > /tmp/output.log`
 * Navigate on your website for few seconds, then kill varnishlog with `Ctrl+C`
 * Get the output file on your computer

Then, **install** this tool:

 * `git clone <this URL>` varnishlog-parser
 * `cd varnishlog-parser`
 * `git submodule init`
 * `git submodule update`

Finally, **execute** the web client:

 * `php -S 127.0.0.1:8080 client.php`
 * Go to http://127.0.0.1:8080/
 * Enter the local path to your `output.log` file in the first form
 * Have fun!

## Contributing

Feel free to submit pull requests. The code is documented, but the logic is still complex (because Varnishlog is).

## Known bugs

This is a week-end project, home alone, so it's not fully tested and there are obviously many hidden bugs. Nevertheless, main unusual use cases have been tested (ESI, restarts, truncated file, synth response, custom vmods, ...).

* The sequence diagram font size is too small when there are too many transactions.



## Todo

* Set apart different clients (by IP)
* Set apart different backends (by backend name or IP)
* Parse and graph cookies
* Add timestamps
* Complete documentation

## Thanks

Special thanks to :

 * Poul-Henning Kamp and all the (Varnish Software)[https://www.varnish-software.com/] team
 * Shohei Tanaka for the [Vsltrans](http://vsltrans.varnish.jp/) project
 * Websequencediagrams.com and its nice (and free!) API
 * [Kint](http://raveren.github.io/kint/), `print_r()` on steroids


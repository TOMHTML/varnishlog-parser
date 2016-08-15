# Varnishlog Parser

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.txt)
[![Maintenance](https://img.shields.io/maintenance/yes/2016.svg?maxAge=2592000)](https://twitter.com/TOMHTML)

A standalone tool to transform a Varnish output file into a simple diagram sequence.

![Screenshot](images/example_output.png)

## Requirements

 * Varnish 4.1
 * PHP 5 (tested with PHP 5.6) or PHP 7.0
 * cURL (`sudo apt-get install php5-curl` or `php-curl`)
 * Internet access to websequencediagrams.com (generated diagram)

## Installation and usage

First, you need to **generate a varnishlog output**.

 * Log in your Varnish server
 * Execute this command: `varnishlog -g raw > /tmp/output.log`
 * Navigate on your website for few seconds, then kill varnishlog with <kbd>Ctrl</kbd>+<kbd>C</kbd>
 * Get the output file on your computer

Then, **install** this tool:

 * `git clone https://github.com/TOMHTML/varnishlog-parser.git varnishlog-parser`
 * `cd varnishlog-parser`
 * `git submodule update --init`, to download the Kint library

Finally, **execute** the web client as a standalone tool:

 * `php -S 127.0.0.1:8080 client.php`
 * Go to http://127.0.0.1:8080/
 * Enter the local path to your `output.log` file in the first form, or upload the file
 * Have fun!

Feel free to install _Varnishlog Parser_ on a private web server like Apache or Nginx.

## Contributing

Feel free to submit pull requests. The code is documented, but the logic is still complex (because Varnishlog is).

## Known bugs

This is a week-end project, home alone, so it's not fully tested and there is obviously a handful of hidden bugs. Nevertheless, main unusual use cases have been tested (ESI, restarts, truncated file, synth responses, custom vmods, background fetches...).

* In some edge cases, transactions order might not be fully respected.
* Very big files might cause timeout errors. Increase the value of `max_execution_time` and `upload_max_filesize` in `php.ini` to fix that.


## Todo

* Set apart different clients (by IP)
* Set apart different backends (by backend name or IP)
* Parse and graph cookies
* Add timestamps
* Complete documentation


## License

See the [LICENSE](LICENSE.txt) file for license rights and limitations (MIT).

## Thanks

Special thanks to :

 * Poul-Henning Kamp and all the [Varnish Software](https://www.varnish-software.com/) team
 * Shohei Tanaka for the [Vsltrans](http://vsltrans.varnish.jp/) project
 * [Websequencediagrams.com](https://www.websequencediagrams.com/) and its nice (and free!) API
 * [Kint](http://raveren.github.io/kint/), `print_r()` on steroids


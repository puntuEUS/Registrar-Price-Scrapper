var casper = require('casper').create({
    pageSettings: {
        javascriptEnabled: true,
        loadImages:  false,        // do not load images
        loadPlugins: false         // do not load NPAPI plugins (Flash, Silverlight, ...)
    }
});

var x = require('casper').selectXPath;

var url = casper.cli.get(0);
var XPath = [];

var error = false;

// Returns a randomly picked user agent
function userAgent() {
    var user_agent = [
        'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36',
        'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:41.0) Gecko/20100101 Firefox/41.0',
        'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.101 Safari/537.36',
        'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2490.80 Safari/537.36',
        'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2490.71 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11) AppleWebKit/601.1.56 (KHTML, like Gecko) Version/9.0 Safari/601.1.56',
        'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2490.80 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit/601.2.7 (KHTML, like Gecko) Version/9.0.1 Safari/601.2.7',
        'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; rv:11.0) like Gecko',
        'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; AS; rv:11.0) like Gecko',
        'Mozilla/5.0 (compatible, MSIE 11, Windows NT 6.3; Trident/7.0; rv:11.0) like Gecko',
        'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/5.0)',
        'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_3) AppleWebKit/537.75.14 (KHTML, like Gecko) Version/7.0.3 Safari/7046A194A',
        'Opera/9.80 (X11; Linux i686; Ubuntu/14.10) Presto/2.12.388 Version/12.16',
        'Opera/9.80 (Windows NT 6.0) Presto/2.12.388 Version/12.14',
    ];
    return user_agent[Math.floor(Math.random() * user_agent.length)];
}

casper.userAgent(userAgent());

if (url === undefined) {
    casper.echo("URL missing!");

    error = true;
}

// Loops through all the XPaths and puts them in an array
for (var i = 1; casper.cli.has(i); i++) {
    XPath.push(casper.cli.get(i));
}

if (!error) {
    // Sets the user agent
    casper.userAgent(userAgent());

    // The viewport it's set so the website is loaded as desktop and not for mobiles
    casper.start(url).viewport(1920, 1080);

    // Reloads the website because sometimes the prices appear the second time the page is loaded
    casper.reload();

    casper.waitForSelector(x("//*[@id='category-0']"), function () {
        this.click(x("//*[@id='category-0']"));
    }, function _onTimeout(){
        this.echo("");
    });

    casper.waitForSelector(x(XPath[0]), function () {
        for (var y = 0; XPath.length > y; y++) {
            if (casper.exists(x(XPath[y]))) {
                this.echo((this.fetchText(x(XPath[y]))).replace(/\s+/g, ''));
            } else {
                this.echo("");
            }
        }
    }, function _onTimeout(){
        this.echo("");
    });

    casper.then(function () {
        casper.exit();
    });

    casper.run();
} else {

    casper.echo("Errors have been detected. Exiting.");
    casper.exit();
}
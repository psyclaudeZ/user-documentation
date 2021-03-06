# Async

Hack provides a feature called **async** that provides your program the benefit of cooperative multi-tasking. It allows code that utilizes the async infrastructure to hide input/output (I/O) latency and data fetching. So, if you have code that has operations that involve some sort of waiting (e.g., network access, waiting for database queries), async minimizes the downtime your program has to be stalled because of it as the program will go do other things, most likely further I/O somewhere else.

Async is **not multithreading** - HHVM still executes all of your PHP/Hack code in one main request thread - but other operations (eg MySQL queries) can now execute without taking up time in that thread that your code could be using.

## A Page As A Dependency Tree

Imagine you have a page that contains two components; one stores data in MySQL, the other fetches from an API via cURL - and both cache results in Memcached. The dependencies could be modeled like this:

![Dependency Tree](/images/async/async-dependency.png)

Code structured like this gets the most benefit from async.

## Synchronous/Blocking IO: Sequential Execution

If (like most PHP code) you do not use asynchronous programming, each step will be executed one-after-the-other:

![Sequential Execution](/images/async/async-sequential.png)

## Asynchronous Execution

All PHP/Hack code executes in the main request thread, but I/O does not block it, and multiple I/O or other async tasks can execute concurrently. If your code is constructed as a dependency tree and uses async I/O, this will lead to various parts of your code transparently interleaving instead of blocking each other:

![Asynchronous](/images/async/async-always-busy.png)

Importantly, the order your code executes is not guaranteed - for example, if the cURL request for Component A is slow, execution of the same code could look more like this:

![Asynchronous with slow cURL](/images/async/async-slow-curl.png)

The reordering of different task instructions in this way allow you to hide I/O [latency](https://en.wikipedia.org/wiki/Latency_\(engineering\)). So while one task is currently sitting at an I/O instruction (e.g., waiting for data), another task's instruction, with hopefully less latency, can execute in the meantime.

Async is not a panacea, however. While executing two async functions can utilize the task reordering mechanisms described here, if those async functions call blocking functions like [`curl_exec()`](php.net/manual/en/function.curl-exec.php) or `sleep()`[php.net/manual/en/function.sleep.php], those calls are not cooperatively multi-tasking and will be blocked as normal.

## Async In Practice: cURL

A naive way to make two cURL requests without async could look like this:

@@ introduction-examples/non-async-curl.php @@

In the example above, the call to `curl_exec` in `curl_A()` is blocking any other processing. Thus, even though `curl_B()` is an independent call from `curl_A()`, it has to sit around waiting for `curl_A()` to finish before beginning its execution.

![No Async](/images/async/curl-synchronous.png)

@@ introduction-examples/async-curl.php @@

In this example, we are calling an async-aware version of `curl_exec()`. Thus, in this case, our waiting state is explicitly allowing other I/O operation tasks in the code to occur.

When `curl_A()` hits a call to `HH\Asio\curl_exec`, depending on, for example, the network latency to retrieve results of the cURL, the async infrastructure (the scheduler) looks for other async tasks that could be run. It finds that `curl_B()` is available to execute, so it starts executing that code. When it hits its `HH\Asio\curl_exec()` call, the process is repeated again, and the scheduler will find that our `curl_exec()` call in `curl_B()` is ready for execution once again.

![Async](/images/async/curl-async.png) 

While this example may not always show a measurable time savings (there are some factors like network latency and possible caching involved), you will not be slower than the non-async version overall and you may get results like this:

*Non-Async*
```
Total time taken: 3.3065688610077 seconds
```

*Async*
```
Total time taken: 2.3396739959717 seconds
```

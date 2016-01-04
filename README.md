# mg-reverse-proxy

MG-Reverse-Proxy provides a bridge between your application and the Symfony HTTP Cache.  It works by transforming the output buffer and headers created by your application into a Symfony Reponse object that the can be intellegently cached.

In general, MG-Reverse-Proxy will cache responses that are set to PUBLIC and have a non-zero MAX-AGE, and will only cache 'safe' HTTP methods.  There are many methods available to customize your response headers provided by the Symfony Response Object.

Since MG-Reverse-Proxy is a simple wrapper around the Symfony HTTPCache, the documentation provided by Symfony will be helpful in understanding the internals of  MG-Reverse-Proxy.  For more information on the Symfony HTTPCache see: http://symfony.com/doc/current/book/http_cache.html

## Use Cases
Content heavy sites are a good candidate for MG-Reverse-Proxy.  You can customize the cacheability
of the responses by setting cache headers in your application or configuring your cache adapter (described below).    

You can use MG-Reverse-Proxy in situations where you want to speed up your application, but installing a dedicated Caching solution like Varnish might not be posible or available.  In contrast, MG-Reverse-Proxy is written in PHP which might be easier deploy.

## Benefits of caching...

Initial Request:

    Request -> Is Caching Enabled?  
            -> MG-Reverse-Proxy bootstraps your application
            -> Your application generates webpage (multiple hits to database)
            -> MG-Reverse-Proxy captures the outputbuffer and transforms it into a Response object
            -> The cache adapter sets headers if applicable
            -> The Response object is returned to the Symfony HTTPCache
            -> The Symfony HTTPCache Stores Result in local cache 
            -> Finally, the response is returned to user

Subsequent Requests:

    Request -> Is Caching Enabled?   
            -> MG-Reverse-Proxy 
            -> Generated webpage pulled from cache
            -> Returns cached response to user
            
    Note: With a cached response, your application is not bootstrapped at all!

## Cache Adapters
Configuration of MG-Reverse-Proxy is handled through cache adapters.  Included in the source code is a generic adapter, and one for WordPress.   If you want to write your own adapter implement the **CacheAdapterInterface**.  

Notes: The WordPress adapter was developed to allow developers to quickly cache their WordPress sites.  The WordPress adapter will cache all responses as long as the user isn't logged in.  To do this the WordPress adapter sets cache header values.  If your application already sets cache headers, or you utilize a plugin like w3-total-cache, use the Generic Adapter instead since it will respect the Headers set by your application.

## Stores
Symfony HTTPCache has the concept of a cache store.  By default, this is a local directory on the file system.
If you want to use a different caching strategy (memcache, redis...), you can create your own implementation of the **StoreInterface**.

##Example Usage - Wordpress index.php
The index.php file for WordPress looks like...

    <?php
    include_once(dirname( __FILE__ ) . '/wp-blog-header.php');

To enable caching for your WordPress application, you instantiate a cache store (which is a local directory for this example).  We instantiate a new CachedReverseProxy object, using the WordPress adapter, pass along the path to the bootstrap file, and the default MaxAge (which is 600 seconds in this example).

    <?php 
    include_once(__DIR__ . '/../../application/vendor/autoload.php');

    use Symfony\Component\HttpKernel\HttpCache\Store;
    use Mindgruve\ReverseProxy\CachedReverseProxy;
    use Mindgruve\ReverseProxy\Adapters\WordPressAdapter;

    $store = new Store(dirname(__FILE__) . '/wp-content/cache');
    $reverseProxy = new CachedReverseProxy(new WordPressAdapter(dirname( __FILE__ ) . '/wp-blog-header.php', 600, $store));
    $reverseProxy->run();
      

## Modifying a Adapter
There are a number of entry points that you can use to modify the behavior of MG-Reverse-Proxy.  To use a custom wordpress adapter, extend the included class and overwrite the relevant method.  Or you can write your own adapter by implementing the ReverseCacheInterface.

Here is a description of the important methods of your custom adapter:

**isCachingEnabled** (bool) - If true, caching is enabled.  If false, caching will be turned off, and all responses will hit the WordPress application.  

The default of the Generic adapter is true, while the default behavior of the WordPress adapter is to turn off caching anytime the user is logged in.    

**isShutdownFunctionEnabled** (bool) - If true, MG-Reverse-Proxy will register a shutdown function to capture output sent to the user after an exit() call.  This is useful for API-Like calls that often exit after echoing their response.  

The default is true for both the Generic and the WordPress adapter.    

**setCacheHeaders** (Response) - This method is called to allow you to set custom cache headers.  Using this method you can mark certain methods as public.  T

he Generic adapter will not set any Headers, and will respect the cache headers set by your application.  By default, the WordPress adapter will set this to private if the user is logged in, and public otherwise. This means all responses for anonymous users will be cached.

**bootstrap** (Void) - This method is called to bootstrap your application.  

**getRawContent** (string) - This method returns back a string of the output buffers, that MG-Reverse-Proxy converts to a Symfony Response object.       

**getStore** (StoreInterface) - Returns the store that the HTTPCache can use to cache responses.  

**getSurrogate** (SurrogateInterface) - Useful for integration with Varnish.  See the Symfony documentation for more information.    

## Example - Updating WordPress Adapter - Marking Contact Page as Private
Say you have a WordPress site with a contact page, and you are using MG-Reverse-Proxy to speed up the responsiveness of your site.  It all works well, except you have a contact form on the url **/contact**.   Caching this page is problematic because the CSRF token, and validation errors will get cached.  Remember, by default, the WordPress adapter will cache any page viewed by a anonymous user.  

To override this behavior, create a new class...

    use Mindgruve\ReverseProxy\Adapters\WordPressAdapter;
    
    class CustomWordpressAdapter extends WordPressAdapter {
    
        /**
         * @param Request $request
         * @param Response $response
         * @return Response
         */
        public function setCacheHeaders(Request $request, Response $response)
        {
            if(preg_match('/^contact', $request->getRequestUri()){
                $response->setPrivate;
                return response;
            }
        
            return parent::setCacheHeaders($request, $response)
        }
    }

Notice that we return a $response object with the cache header set to private (ie... do not cache) if the url matches  /contact.  

To use this custom adapter, update your index.php file...

    $reverseProxy = new CachedReverseProxy(new CustomWordpressAdapter(dirname( __FILE__ ) . '/wp/wp-blog-header.php', 600, $store));

Note:  Again, the WordPress adapter is meant to be a quick way for developers to cache their WordPress application.  The setCacheHeaders function can become very large if your busines rules to determine which pages should be cached are complex.  At some point, it probably makes more sense to refactor so that this logic inside your WordPress application, and then use the Generic adapter instead since it will simply cache based on the headers it receives from your application.

## Bugs / Patches / Features
Contributions are welcomed.  Feel free to fork and submit a pull request.  


## License

MIT License
Copyright (c) 2016 Mindgruve

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

# Sonata Media Crop

This Bundle uses jcrop library for image cropping and needs FOS Js Routing bundle for routes in js file 
MediaCroppingBundle extends bundle ApplicationSonataMediaBundle make sure you have this extended bundle

This Bundle needs FOS Js Routing bundle for routes in js file

Import configuration for this bundle in `config.yml`

	imports:
	    - { resource: @MediaCroppingBundle/Resources/config/twig.yml }
	    - { resource: @MediaCroppingBundle/Resources/config/media_cropping.yml }

Import routing for these bundles in main `routing.yml`

	media_cropping:
	    resource: "@MediaCroppingBundle/Resources/config/routing.yml"
	    prefix:   /

	fos_js_routing_js:
	    resource: "@FOSJsRoutingBundle/Resources/config/routing/routing.xml"

Enable bundle in `AppKernel.php`

	new FOS\JsRoutingBundle\FOSJsRoutingBundle(),
	new Media\CroppingBundle\MediaCroppingBundle(),

Override sonatas "standard_layout.html.twig" and add the following lines somewhere between <head> and </head> place:

    {% javascripts
      '@MediaCroppingBundle/Resources/public/js/jquery-ui.js'
      '@MediaCroppingBundle/Resources/public/js/jquery.Jcrop.js'
      '@MediaCroppingBundle/Resources/public/js/TrafficAdmin.js' %}
      <script src="{{ asset_url }}"></script>
    {% endjavascripts %}

    {% stylesheets
      '@MediaCroppingBundle/Resources/public/css/css.css'
      '@MediaCroppingBundle/Resources/public/css/jquery.Jcrop.css'
    filter='cssrewrite' %}
      <link rel="stylesheet" href="{{ asset_url }}"/>
    {% endstylesheets %}

    <script src="{{ asset('bundles/fosjsrouting/js/router.js') }}"></script>
    <script src="{{ path('fos_js_routing_js', {"callback": "fos.Router.setData"}) }}"></script>

Usage

You can configure sizes under `media_cropping` node in `media_cropping.yml` that you want to use for cropping media

    media_cropping:
        sizes:
            icon:   { width: 55 , height: 55 , quality: 90}
            small:  { width: 100 , height: 100 , quality: 90}
            banner: { width: 1366 , height: 320 ,quality: 90}

Lastly assetic dump and update database `app/console doctrine:schema:update --force` or use mediacropping.sql file
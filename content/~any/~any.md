# {{page.meta('taxonomy_meta.term')}}

{% for item in page.meta('taxonomy_meta.pages.page') %}
* {{item.url|link}}
{% endfor %}

{% include 'partials/paginator.twig' with {'arg': 'page','page': page.meta('taxonomy_meta.page'),'pageCount': page.meta('taxonomy_meta.pageCount')} %}

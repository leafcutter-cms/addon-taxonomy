# {{page.meta('taxonomy_meta.name')}}

{% for term in page.meta('taxonomy_meta.terms.page') %}
* {{term.url|link}} ({{term.count}} pages)
{% endfor %}

{% include 'partials/paginator.twig' with {'arg': 'page','page': page.meta('taxonomy_meta.page'),'pageCount': page.meta('taxonomy_meta.pageCount')} %}

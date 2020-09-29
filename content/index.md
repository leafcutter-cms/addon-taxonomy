# Page taxonomies

{% for t in page.meta('taxonomy_meta') %}
* {{t.url|link}}
{% endfor %}

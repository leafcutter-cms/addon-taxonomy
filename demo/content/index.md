# Taxonomies addon demo

{% for page in page.children %}
* {{page|link}}
{% endfor %}

## Default taxonomies

### Tags

Tags can be created via a #hashtag or via metadata.

Here's one that conflicts with the ones in the text: #testtag

<!--@meta 
name: Home
taxonomy:
    tags: [home, TestTag, demo]
 -->

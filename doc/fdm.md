==========================
Data and Text fundamentals
==========================

when you need to deal with data, what fundamental things you need
=================================================================

all programming language syntax to access data, but every project still need extra
tool on it, which means language itself miss something essential.

- e.g. tree structure is very common:
a = {
  title   :'Good article'
  summary : 'Lets take a look on this good article...'
  content :'
    this is the content of this article and it is so great. it is about nothing. that all.
  '
  preview_image : {
    'src' : '../images/prview.png'

  }
}

- data structure need design. once design and applied on some usage, it cannot not
be change since it will broken the previous usage

- but something, design changes are only some relocation work. which means the
previous piece of data still there but only moved to other place inside the object
or used a different wrapper or have different format.

- someone suggested a lookup language to search for information

- xPath on XML do that job

common usage:
============
- we want to get a string from user input but worry about it is not safe from programming code.

- we want to see the color choosed by user, but if they haven't done it,
 we want to see the default one.

- (complex default)
we want to get a language specific string of 'Confirm' with the current selected language:
- if language haven't set, use default one
- if default language haven't set, use english
- if don't have english string, use any language string available
- if there is no any available language string, use the value of key:confirm
- if there is no key:confirm, use the hardcode word 'confirm'
- if there is no language handling library support. simply use key:confirm
- if there is no language support and also don't have key:confirm, use hardcode string 'confirm'


Advance Tree locator:
=====================
single line syntax:
<path> ---- <value_or_path_when_previous_path_return_default> ---- [...] .<casting_type>.<further_type_casting>

multi-line syntax:
<path>
----------
<value_or_path_when_previous_path_return_default>
----------
[...]

.<casting_type>


<path> : string start with /
  - path constructed by node or index value e.g.
    - /<node_or_index>/<child_node_or_index> [.type]/...
      <node>  : a property name in a object, (name should only contain a-z,0-9 or _ and must start with a-z, - will convert to _)
      <index> : index value in array .e.g.
        - <numeric_value>          : 8899
        - <special_meaning_string> : [__last__]
      if .xxxx added at the end of node/index, it means type casting to xxxx

<value> :
- string : string quoted by '' or string cannot be parsed to number or boolean etc
- number : string can parsed to number (do not contain any non-numerical characters)


staff_list = [
{
  id : 8899
  , position : [
    'web developer'
    , 'it support'
  ]
  , title : 'Mr'
  , name : {
    en : 'ivan'
    , tc : '蝦仁'
  }
  email : [
    'ivan@a.com'
    , 'ivan@gmail.com'
  ]
}
, {
  id : 1122
  , title : 'Ms'
  , name : {
    en : 'Mary'
    , tc : '馬利'
  }
}

];

- //1/name/en       : fullpath to 'Mary'
- //0/email.array   : return a list of email in array: ['ivan@a.com', 'ivan@gmail.com']
- //0/email.string  : return the default property of email and cast it to string.
                      - if the email is an array, [__first__] element will be return
                      - if the email is a object, the value in ['__default__'] property will be return
                      - if the email is an array and [__first__] is a object, the
                        value in [__first__]['__default__'] property will be return
                      - the name of default property or default index can be change

- index value represented by number e.g. 8899
- special index value represented by string start and end with [...] e.g.
  - [__first__] : the first element
  - [__last__]  : the last element
  - [8]     : put number inside is still work
  - [2-8]   : a range if elements
  - [__all__]   : equal to the effect of omit the index value
  - [id==8899] : the element with the id equal to 8899
  - [__first__,2,4,5] : return a list of element with index: 0,2,4,5
  note that:
  - number start with 0
  - the value may not what you expect
    e.g. when the array is empty, geta('/1/email/[last]') with return "__default__"


- value can be refer by path when the string is start and end with {{...}}
  e.g. when mary don't have email address, use it support team's email
  //[name == Mary, position ==it support, department==it department,__last__]
    /email
      ----support@a.com
  .emailTag

  output:
  <a href="mailto:xxx@xxx.com">xxx@xxx.com</a>

template of .emailTag
<a href="mailto:{{email, address}}">{{email, address}}</a>
if cast type changed to .html it will be a full page of standard html with only on email link

fallback:
=========
- any path return the value __default__ will fallback to things point after '----'
- the actual value of __default__ will be transform base on their type defined
  e.g. :
  - string: __default__
  - array : array()
  user can define their own type (object template), __default__ will be base on template's default value

whitespace:
===========
- only whitespace between quote will be reserved

type casting:
=============
- it will try to modify the value so that it can be cast to target type
- e.g.:
  - unaccepted characters will be replaced by '_' when cast to ::key
  - unaccepted tag will be cleanUp on safeHtml


standard type:

.string (default, can be omit)
.number
.int
.boolean

.array
.object (for php it will be stdClass object)
.asso_array (when javascript it will be object)
.json

extended type:

.id
.key
.is
.email
.guid
.safeHtml      - not contain tag like <script>
.cleanHtmlNode -
.html  - full html page outout

.string_list
.number_list
.int_list
.boolean_list
.id_list
.key_list
.is_list
.email_list
.guid_list


template:
=========
- a string which have markup to represent a variable {{...}}
- variable can be pointed by a path or literal value.
- string marked as __???__ have special function:
  - direct access some global available value
  - programmatic value not be accessable by path e.g. :
    - the current key for this value
    - the name of parent node for this key
  - special tag/attribute removal process

- since template can be without variable, that means very template can be a pain string
- we can extended the meaning of string become: very string should be a template:
  e.g. :
  /lang_dict/ui_btn/submit/en --> 'submit'
  /ui/contact/form/submit_btn  can pointed to --> 'please {{/lang_dict/ui_btn/submit}}'


- ordinary template always have a fixed set of property to access. but data always
  a various morphing. to due with this situation, some tool using name mapping on
  data. some simply provide another template which almost the same as original but
  with some property name changed.

- we use: node collection, node lookup, default __first__ in array to provide
  multiply property name options. syntax:
  {{[name1,name2,...].castType}}
  e.g. a link tag with img inside will server the following purposes:
  1. thumbnail of the fullsize image, link to the fullsize image
  2. video poster_frame of video, link to the video
  3. shortcut of a url, link to a page
  1.:
    - image_large_url
    - image_small_url
    - title

  2.:
    - video_url
    - poster_url
    - title
  3.:
    - link_url
    - thumbnail_url
    - name

the following template will serve the above 3 types of data without change a line of code:

<!--start_link-->

<a class="__nodeName__" href="{{[image_large_url, video_url, link_url].url-->__remove_link__}}" title="{{[title, name, __remove_htmlAttr__].htmlAttr}}">
  <!--start_img-->
  <img src="{{[image_small_url, poster_url, thumbnail_url, '__remove_img__'].url}}"/ alt="{{[title,name].htmlAttr----__remove_htmlAttr__}}">
  <!--end_img-->
</a>

<!--alt_link-->

<span class="__nodeName__">
  <img src="__webRoot__/images/place_holder.png" />
</span>

<!--end_link-->

__webRoot__
__nodeName__
__nodePath__
__first__ (first property or index)
__last__  (last property)
__all__   (all property)
__default__
__remove_htmlAttr__
__remove_XXXX__ ( <!--start_XXXX--> / <!--alt_link--> / <!--end_XXXX--> )

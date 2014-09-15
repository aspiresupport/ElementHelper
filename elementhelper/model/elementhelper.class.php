<?php

class ElementHelper
{
	private $modx;
	private $file_helper;

	function __construct(modX $modx)
	{
		$this->modx = $modx;

		$modx->loadClass('Element', $modx->getOption('elementhelper.core_path') . 'model/', true, true);
		$modx->loadClass('FileHelper', $modx->getOption('elementhelper.core_path') . 'model/', true, true);
	}

	/**
	 * Creates categories from an array of category names. Each category is 
	 * the parent of the following category. The last category in the tree 
	 * is returned.
	 * 
	 * @param array $categories
	 * 
	 * @return Element | boolean
	 */
	private function create_category_tree($categories)
	{
		foreach($categories as $category_name)
		{
			$parent_id = (isset($category) ? $category->get_property('id') : 0);
			$category = $this->create_category($category_name, $parent_id);

			if ( ! $category)
			{
				return false;
			}
		}

		// Return the last category made
		return $category;
	}

	/**
	 * Creates and returns a category object
	 * 
	 * @param string $name
	 * @param int $parent_id
	 * 
	 * @return Element | boolean
	 */
	private function create_category($name, $parent_id = 0)
	{
		$category = Element::get($this->modx, 'modCategory', $name);

		if ( ! $category)
		{
			$category = Element::create($this->modx, 'modCategory', $name);
		}

		$properties = array(
			'parent' => $parent_id
		);

		if ($category->set_properties($properties))
		{
			return $category;
		}

		return false;
	}

	/**
	 * Returns a category tree as a forward-slash delimited path. Used to
	 * create file paths when making an element static.
	 * 
	 * @param integer $id
	 * 
	 * @return string
	 */
	private function get_category_tree_path($id)
	{
		if ($id === 0)
		{
			$category = Element::get($this->modx, 'modCategory', $id);
			$path = $category->get_property('name');
		}

		while ($id !== 0)
		{
			$category = Element::get($this->modx, 'modCategory', $id);
			$path = (isset($path) ? $category->get_property('name') . '/' . $path : $category->get_property('name') . '/');
			$id = $category->get_property('parent');
		}

		return $path;
	}

	/**
	 * Gets the meta information for a file element e.g. description
	 * 
	 * @param string $file_content
	 * 
	 * @return array
	 */
	private function get_file_element_meta($file_content)
	{
		$meta = array();
		$comments = FileHelper::get_file_doc_comments($file_content);

		foreach ($comments as $comment)
		{
			$comment_lines = explode("\n", $comment);
			
			foreach($comment_lines as $comment_line)
			{
				if (preg_match('/@Description (.*)/', $comment_line, $match))
				{
					$meta['description'] = trim($match[1]);
				}
			}
		}

		return $meta;
	}

	/**
	 * Gets and returns the properties for a file to be saved into an
	 * element
	 * 
	 * @param string $type_path
	 * @param string $path
	 * 
	 * @return array
	 */
	public function get_file_element_properties($type_path, $path)
	{
		$content = file_get_contents($path);
		$meta = $this->get_file_element_meta($content);

		// Get the files parent directories to use for building the categories
		$category_path = dirname(str_replace(MODX_BASE_PATH . $type_path, '', $path));

		if ($category_path !== '.')
		{
			$categories = explode('/', $category_path);
			$category = $this->create_category_tree($categories);
		}

		$properties = array(
			'source' => 1,
			'static' => 1,
			'static_file' => str_replace(MODX_BASE_PATH, '', $path),
			'description' => (isset($meta['description']) ? $meta['description'] : ''),
			'content' => $content,
			'category' => (isset($category) ? $category->get_property('id') : null)
		);

		return $properties;
	}

	/**
	 * Builds the file path for an element 
	 * 
	 * @param string $type
	 * @param string $type_path
	 * @param string $name
	 * @param string $category
	 * 
	 * @return string
	 */
	public function build_element_file_path($type, $type_path, $name, $category)
	{
		$extension = ($type === 'modTemplate' || $type === 'modChunk' ? '.tpl' : '.php');
		$file_name = $name . $extension;
		$file_path = MODX_BASE_PATH . $type_path;
		$file_path .= ($category === 0 ? $file_name : $this->get_category_tree_path($category) . $file_name);

		return $file_path;
	}

	/**
	 * Creates a doc comment for element meta to be appended to the top of
	 * an elements file.
	 * 
	 * @todo See if there's a better way to do this
	 * 
	 * @param array $meta
	 * 
	 * @return string
	 */
	private function build_meta_doc_comment($meta)
	{
		$output = "<?php /**\n *\n ";

		foreach($meta as $tag => $value)
		{
			$output .= sprintf('* @%s %s', ucfirst($tag), $value);
		}

		$output .= "\n *\n */ ?>\n\n";

		return $output;
	}

	/**
	 * Gets the the properties of an element for it's static file
	 * 
	 * @todo maybe change the name of this
	 * 
	 * @param Element $element
	 * @param string $path
	 * 
	 * @return array
	 */
	public function get_element_static_file_properties($element, $path)
	{
		$meta = array(
			'description' => $element->get_property('description')
		);

		$content = $this->build_meta_doc_comment($meta);
		$content .= $element->get_property('content');

		$properties = array(
			'content' => $content,
			'source' => 1,
			'static' => 1,
			'static_file' => str_replace(MODX_BASE_PATH, '', $path)
		);

		return $properties;
	}

	/**
	 * Gets the properties for a template variable
	 * 
	 * @todo migx stuff
	 * @todo map weird named properties to more sensible ones e.g. input option values is elements
	 * @todo allow processing of additional values for properties like "display" e.g. when it's a url (related to output_properties?)
	 * 
	 * @param object $tv
	 * 
	 * @return array
	 */
	public function get_tv_element_properties($tv)
	{
		$properties = (array) $tv;

		// Properties that require processing beyond just setting the value
		$complex_properties = array(
			'name',
			'category',
			'input_properties',
			'template_access'
		);

		// Remove the complex properties
		foreach ($complex_properties as $property)
		{
			if (array_key_exists($property, $properties))
			{
				unset($properties[$property]);
			}
		}

		// Set up categories
		if (isset($tv->category))
		{
			$category = $this->create_category($tv->category);

			$properties['category'] = ($category ? $category->get_property('id') : 0);
		}
		else
		{
			$properties['category'] = 0;
		}

		return $properties;
	}

	/**
	 * Sets up all template access for a template variable
	 * 
	 * @param integer $tv_id
	 * @param array $templates
	 * 
	 * @return boolean
	 */
	public function setup_tv_template_access($tv_id, $templates)
	{
		$template_collection = $this->modx->getCollection('modTemplate');

		// Remove all tv access for each template
		foreach ($template_collection as $template)
		{
			$template = Element::insert($template);

			if ( ! $this->remove_template_access($tv_id, $template->get_property('id')))
			{
				return false;
			}
		}

		// Give access to all templates if the first name is *
		if ($templates[0] === '*')
		{
			foreach ($template_collection as $template)
			{
				$template = Element::insert($template);

				if ($template)
				{
					if ( ! $this->add_template_access($tv_id, $template->get_property('id')))
					{
						return false;
					}
				}
			}
		}
		else
		{
			foreach($templates as $template_name)
			{
				$template = Element::get($this->modx, 'modTemplate', $template_name);

				// If the template exists add access to the tv
				if ($template)
				{
					if ( ! $this->add_template_access($tv_id, $template->get_property('id')))
					{
						return false;
					}
				}
			}
		}

		return true;
	}

	/**
	 * Adds template access to a template variable
	 * 
	 * @param integer $tv_id
	 * @param integer $template_id
	 * 
	 * @return boolean
	 */
	private function add_template_access($tv_id, $template_id)
	{
		$tv_template = $this->modx->getObject('modTemplateVarTemplate', array(
			'tmplvarid' => $tv_id,
			'templateid' => $template_id
		));

		// If there is no tv template pairing
		if ( ! isset($tv_template))
		{
			$tv_template = $this->modx->newObject('modTemplateVarTemplate');

			$tv_template->set('tmplvarid', $tv_id);
			$tv_template->set('templateid', $template_id);

			if ( ! $tv_template->save())
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Removes template access from a template variable
	 * 
	 * @param integer $tv_id
	 * @param integer $template_id
	 * 
	 * @return boolean
	 */
	private function remove_template_access($tv_id, $template_id)
	{
		$tv_template = $this->modx->getObject('modTemplateVarTemplate', array(
			'tmplvarid' => $tv_id, 
			'templateid' => $template_id
		));

		if (isset($tv_template))
		{
			if ( ! $tv_template->remove())
			{
				return false;
			}
		}

		return true;
	}
}
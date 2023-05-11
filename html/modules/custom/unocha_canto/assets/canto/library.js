(function () {

  'use strict';

  /**
   * Wrapper around the Canto API.
   */
  class CantoApiClient {

    constructor(loadingCallback) {
      this.loadingCallback = loadingCallback;

      this.apiUrl = '/admin/unocha_canto/canto/api';
      this.apiHeaders = {};

      // @todo retrieve that from the iframe attributes.
      this.allowedExtensions = null;

      this.imageList = [];
      this.loadingComplete = false;

      this.offset = 0;
      this.limit = 50;
    }

    async request(endpoint, options = {}, json = true) {
      if (!options.hasOwnProperty('headers')) {
        options.headers = this.apiHeaders;
      }

      // Change the endpoint to a parameter to bypass limitations of the
      // Drupal routing system not being able to capture a route "tail".
      endpoint = '?endpoint=' + endpoint.split('?', 2).join('&').replace(/^[/]/, '');

      const url = this.apiUrl + endpoint;

      try {
        this.toggleLoading(true);
        const response = await fetch(url, options);
        const data = await (json ? response.json() : response.text());
        this.toggleLoading(false);
        return data;
      }
      catch (error) {
        console.log('Error', options.method || 'GET', url, ':', error);
      }
    }

    toggleLoading(loading) {
      if (this.loadingCallback) {
        this.loadingCallback(loading);
      }
    }

    async loadTree() {
      const data = await this.request('/tree?sortBy=name&sortDirection=ascending&layer=-1');
      return data ? data.results : null;
    }

    async loadSubTree(treeId) {
      const data = await this.request('/tree/' + treeId);
      return data ? data.results : null;
    }

    async getListByAlbum(albumId) {
      const list = await this.getList('/album/' + albumId);
      return list;
    }

    async getListByScheme(scheme) {
      let list;
      if (scheme === 'allfiles') {
        list = await this.getListByKeywords(scheme);
      }
      else {
        list = await this.getList('/' + scheme);
      }
      return list;
    }

    async getListByKeywords(scheme, keywords = '') {
      let endpoint = '/search?aggsEnabled=false&keyword=' + keywords;
      if (scheme === 'allfiles') {
        endpoint += '&scheme=' + encodeURIComponent('image|presentation|document|audio|video|other');
      }
      else if (scheme) {
        endpoint += '&scheme=' + scheme;
      }
      const list = await this.getList(endpoint);
      return list;
    }

    async getList(endpoint) {
      const filter = this.getListFilter();
      if (endpoint.indexOf('?') >= 0) {
        endpoint = endpoint + '&' + filter;
      }
      else {
        endpoint = endpoint + '?' + filter;
      }

      const data = await this.request(endpoint);
      if (typeof data === 'object') {
        data.start = data.start || 0;
        // @todo there may be a better way to merge the new results.
        // this.imageList.push.apply(this.imageList, data.results);
        // this.offset = data.start + data.limit + 1;
        // this.loadingComplete = data.found - data.limit <= data.start;
        // @todo update loading overlay.
        return data;
      }
      return null;
    }

    getListFilter() {
      const parameters = new URLSearchParams({
        'sortBy': 'time',
        'sortDirection': 'descending',
        'limit': this.limit,
        'start': this.imageList.length === 0 ? 0 : this.offset
      });
      // @todo we need to inform that we are loading more images if there are
      // already some images in the library otherwise, we need to clear the
      // current list of images.
      return parameters.toString();
    }

    async getDetail(contentId, scheme) {
      const response = await this.request('/' + scheme + '/' + contentId);
      return response;
    }

    getLimit() {
      return this.limit;
    }

  }

  /**
   * Library manager.
   */
  class Library {

    constructor(targetWindow) {
      this.client = new CantoApiClient(this.toggleLoading);
      this.targetWindow = targetWindow;
    }

    async initialize(options = {}) {
      this.selectionLimit = options.selectionLimit || 1;
      this.allowedExtensions = options.allowedExtensions || [];
      this.scheme = options.scheme || 'allfiles';
      this.imageLabelMaxLength = options.labelMaxLength || 150;

      document.addEventListener('click', this.handleClick.bind(this));

      const tree = await this.client.loadTree();

      this.renderTree(tree);

      this.clearSearch();

      const list = await this.client.getListByScheme(this.scheme);

      this.renderImageList(list);

    }

    async loadAlbum(album) {
      const list = await this.client.getListByAlbum(album);

      this.renderImageList(list);
    }

    toggleLoading(loading) {
      document.body.setAttribute('data-loading', loading);
    }

    renderTree(tree, container) {
      if (!tree) {
        return;
      }

      const list = document.createElement('ul');
      list.classList.add(container ? 'canto-tree-subtree' : 'canto-tree-root');

      tree.forEach(item => {
        const listItem = document.createElement('li');
        listItem.setAttribute('data-id', item.id);
        listItem.setAttribute('data-scheme', item.scheme || '');

        if (item.children && item.children.length) {
          listItem.classList.add('canto-tree-branch');

          const details = document.createElement('details');
          details.classList.add('canto-tree-label');
          details.classList.add('canto-tree-switch');

          const summary = document.createElement('summary');
          summary.appendChild(document.createTextNode(item.name));
          details.append(summary);

          this.renderTree(item.children, details);
          listItem.appendChild(details);
        }
        else {
          listItem.classList.add('canto-tree-leaf');

          const label = document.createElement('span');
          label.appendChild(document.createTextNode(item.name));
          label.classList.add('canto-tree-label');

          if (item.scheme && item.scheme === 'album') {
            label.setAttribute('data-tree-album', item.id);
          }

          listItem.appendChild(label);
        }

        list.appendChild(listItem);
      });

      container = container || document.getElementById('canto-tree');
      container.appendChild(list);
    }

    clearSearch() {
      const search = document.getElementById('canto-search-input');
      if (search) {
        search.value = '';
      }
    }

    renderImageList(data) {
      const results = document.getElementById('canto-results');
      const empty = document.getElementById('canto-empty-list');
      const list = document.getElementById('canto-asset-list');

      // @todo show a "No items found" message if there is no content in
      // the list.
      if (!data || !data.results || data.results.length === 0) {
        results.textContent = '';
        empty.removeAttribute('hidden');
        list.setAttribute('hidden', '');
      }
      else {
        // Update the results.
        const formatter = new Intl.NumberFormat('en-US');
        const start = data.start || 0;
        const end = start + this.client.getLimit();
        const total = data.found || data.results.length;
        results.textContent = [
          'Showing ',
          formatter.format(start + 1),
          ' - ',
          formatter.format(end > total ? total : end),
          ' of ',
          formatter.format(total),
          ' items.'
        ].join('');

        // Hide the empty message.
        empty.setAttribute('hidden', '');

        const fragment = document.createDocumentFragment();

        data.results.forEach(item => {
          const image = this.createImage(item);
          if (image) {
            fragment.appendChild(image);
          }
        });

        list.removeAttribute('hidden');
        list.replaceChildren(fragment);

        this.updateSelection();

        // @todo handle loading more images when scrolling or simpler, add a
        // button to add more or use a pager.
      }
    }

    createImage(data) {
      const extension = data.name.substring(data.name.lastIndexOf('.') + 1).toLowerCase();
      if (!this.isAllowedExtension(extension)) {
        return;
      }

      // Truncate the image label if it's too long so it displays nicely.
      // @todo remove if we can handle in a better way via javascript.
      let label = data.name;
      if (label.length > this.imageLabelMaxLength) {
        label = label.substr(0, 142) + '...' + label.substr(-5);
      }

      const container = document.createElement('div');
      container.classList.add('canto-image');
      container.setAttribute('data-id', data.id);
      container.setAttribute('data-url', data.url.directUrlOriginal);
      container.setAttribute('data-name', data.name);
      container.setAttribute('data-description', data.description);
      container.setAttribute('data-copyright', data.copyright);
      container.setAttribute('data-height', data.height);
      container.setAttribute('data-width', data.width);
      container.setAttribute('data-scheme', data.scheme);
      container.setAttribute('data-size', data.size);

      const image = document.createElement('img');
      image.setAttribute('id', data.id);
      image.setAttribute('src', data.url.directUrlPreview);
      image.setAttribute('alt', data.description);
      image.setAttribute('loading', 'lazy');
      image.setAttribute('data-loading', true);

      const imageLabel = document.createElement('div');
      imageLabel.classList.add('canto-image-name');
      imageLabel.appendChild(document.createTextNode(label));

      const selected = document.createElement('input');
      selected.classList.add('visually-hidden');
      selected.setAttribute('type', this.selectionLimit == 1 ? 'radio' : 'checkbox');
      selected.setAttribute('name', 'selection');
      selected.setAttribute('id', data.id + '-selected');

      const selectedLabel = document.createElement('label');
      selectedLabel.classList.add('canto-image-select');
      selectedLabel.setAttribute('for', data.id + '-selected');
      selectedLabel.appendChild(document.createTextNode('select'));

      container.appendChild(image);
      container.appendChild(imageLabel);
      container.appendChild(selected);
      container.appendChild(selectedLabel);

      return container;
    }

    async handleClick(event) {
      const target = event.target;
      if (target.getAttribute('name') === 'selection') {
        // @todo display message if the selection has reached the limit and
        // uncheck the item. Alternatively, disable the non selected checkboxes
        // when reaching the limit.
        this.updateSelection();
      }
      else if (target.hasAttribute('data-tree-album')) {
        const album = target.getAttribute('data-tree-album');
        if (album) {
          const list = await this.client.getListByAlbum(album);
          this.renderImageList(list);
        }
      }
      else if (target.id === 'canto-search-button') {
        const search = document.getElementById('canto-search-input').value;
        if (search) {
          const list = await this.client.getListByKeywords(this.scheme, search);
          this.renderImageList(list);
        }
      }
    }

    updateSelection() {
      const selection = document.querySelectorAll('input[name="selection"]');

      const data = [];
      let selected = 0;

      // @todo review to disable inputs when over the selection limit.
      for (let i = 0, l = selection.length; i < l; i++) {
        const element = selection[i];
        selected += element.checked ? 1 : 0;

        // Uncheck and disable elements if the selection is over the limit.
        if (this.selectionLimit > 0) {
          if (selected > this.selectionLimit) {
            element.checked = false;
          }
        }

        // Retrieve the asset information.
        if (element.checked) {
          const parent = element.closest('.canto-image');
          data.push({
            id: parent.getAttribute('data-id'),
            url: parent.getAttribute('data-url'),
            name: parent.getAttribute('data-name'),
            description: parent.getAttribute('data-description'),
            copyright: parent.getAttribute('data-copyright'),
            height: parent.getAttribute('data-height'),
            width: parent.getAttribute('data-width'),
            size: parent.getAttribute('data-size')
          });
        }
      }

      // Let the parent window know of the selection.
      this.postMessage('updateSelection', data);
    }

    isAllowedExtension(extension) {
      return this.allowedExtensions.length === 0 || this.allowedExtensions.includes(extension);
    }

    postMessage(type, data) {
      // @todo set an origin (ex: window.origin).
      this.targetWindow.postMessage({type, data}, window.origin);
    }

  }

  // Library instance.
  const library = new Library(window.parent);

  window.addEventListener('message', function (event) {
    if (event.source !== window.parent) {
      return;
    }

    const {type, data} = typeof event.data === 'object' ? event.data : {};

    switch (type) {
      case 'initialize':
        library.initialize(data);
        break;
    }
  });

  window.parent.postMessage({type: 'ready'}, window.origin);

})();

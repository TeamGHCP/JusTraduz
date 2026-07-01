(function() {
  const base = document.querySelector('script[src*="main.js"]')?.src || '';
  const basePath = base.substring(0, base.lastIndexOf('/') + 1) + 'modules/';
  
  const modules = [
    'opening.js',
    'navigation.js',
    'helpers.js',
    'scroll-reveal.js',
    'marquee.js',
    'feature-flow.js',
    'tabs.js',
    'phone-demo.js',
    'security.js'
  ];

  modules.forEach(file => {
    const script = document.createElement('script');
    script.src = basePath + file;
    script.async = false;
    document.head.appendChild(script);
  });
})();

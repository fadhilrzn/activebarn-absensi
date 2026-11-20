//
if (document.getElementById('scanner')) {
    import('./scan.js').then((module) => module.initScanner());
}
import './bootstrap';

// Contract widgets (and ethers) load only on pages that use them.
if (document.querySelector('[data-nx-chain]')) {
    import('./nexgrow/index.js').then(({ init }) => init());
}

/**
 * MHM Rentiva - Module Loader
 * 
 * Dynamically loads and manages JavaScript modules.
 */

(function ($) {
    'use strict';

    window.MHMRentiva = window.MHMRentiva || {};

    /**
     * Module Loader
     */
    MHMRentiva.ModuleLoader = {

        /**
         * Loaded modules
         */
        loadedModules: new Map(),

        /**
         * Module dependencies
         */
        dependencies: new Map(),

        /**
         * Module loading queue
         */
        loadQueue: [],

        /**
         * Define a module
         * @param {string} moduleName - Module name
         * @param {Array} deps - Dependencies
         * @param {Function} factory - Module factory function
         */
        define: function (moduleName, deps, factory) {
            if (typeof deps === 'function') {
                factory = deps;
                deps = [];
            }

            this.dependencies.set(moduleName, deps);
            this.loadedModules.set(moduleName, {
                factory: factory,
                instance: null,
                loaded: false
            });
        },

        /**
         * Require a module
         * @param {string} moduleName - Module name
         * @returns {*} Module instance
         */
        require: function (moduleName) {
            const module = this.loadedModules.get(moduleName);
            if (!module) {
                throw new Error(`Module '${moduleName}' not found`);
            }

            if (!module.loaded) {
                this.loadModule(moduleName);
            }

            return module.instance;
        },

        /**
         * Load a module
         * @param {string} moduleName - Module name
         */
        loadModule: function (moduleName) {
            const module = this.loadedModules.get(moduleName);
            if (!module || module.loaded) return;

            const deps = this.dependencies.get(moduleName) || [];
            const resolvedDeps = deps.map(dep => this.require(dep));

            module.instance = module.factory.apply(null, resolvedDeps);
            module.loaded = true;
        }
    };

    // Global define and require functions
    window.define = MHMRentiva.ModuleLoader.define.bind(MHMRentiva.ModuleLoader);
    window.require = MHMRentiva.ModuleLoader.require.bind(MHMRentiva.ModuleLoader);

})(jQuery);

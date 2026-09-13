import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

/**
 * Construction des ressources du module.
 *
 * Un module ne peut pas compter sur l'application hôte pour compiler ses
 * feuilles de style : elle a sa propre chaîne d'assets, ses propres
 * entrées Vite, et n'a aucune raison de connaître les nôtres. Les
 * ressources sont donc construites ici, sous des noms stables et sans
 * manifeste, puis versionnées dans `public/build/` et copiées chez l'hôte
 * par `php artisan vendor:publish --tag=dme-assets`.
 */
export default defineConfig({
    plugins: [tailwindcss()],
    // Le répertoire public/ du module contient déjà ses images : Vite ne
    // doit pas le recopier dans public/build.
    publicDir: false,
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        manifest: false,
        rollupOptions: {
            input: {
                app: 'resources/js/app.js',
            },
            output: {
                entryFileNames: '[name].js',
                chunkFileNames: '[name].js',
                assetFileNames: '[name].[ext]',
            },
        },
    },
});

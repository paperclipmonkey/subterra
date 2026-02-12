import globals from "globals";
import pluginVue from "eslint-plugin-vue";
import { createRequire } from "module";

const require = createRequire(import.meta.url);
const autoImport = require("./.eslintrc-auto-import.json");

export default [
    {
        files: ["**/*.{js,mjs,cjs,vue}"],
        languageOptions: {
            globals: {
                ...globals.browser,
                ...globals.node,
                ...autoImport.globals
            }
        }
    },
    ...pluginVue.configs["flat/recommended"],
    {
        rules: {
            'vue/multi-word-component-names': 'off',
            // Disable formatting rules that break Vue templates
            'vue/max-attributes-per-line': 'off',
            'vue/singleline-html-element-content-newline': 'off',
            'vue/multiline-html-element-content-newline': 'off',
            'vue/html-closing-bracket-newline': 'off',
            'vue/first-attribute-linebreak': 'off',
            'vue/valid-v-slot': 'off',
            'vue/no-unused-components': 'off',
        }
    },
    {
        ignores: ["dist/**", "node_modules/**", "coverage/**", "playwright-report/**", "test-results/**"]
    }
];

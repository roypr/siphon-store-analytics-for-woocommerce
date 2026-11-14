import js from "@eslint/js";
import css from "@eslint/css";
import globals from "globals";
import { defineConfig } from "eslint/config";

export default defineConfig([
  { 
    files: ["**/*.{js,mjs,cjs}"], 
    plugins: { js }, 
    extends: ["js/recommended"], 
    languageOptions: { globals: globals.browser } 
  },
  { 
    files: ["**/*.js"], 
    languageOptions: { sourceType: "module" } 
  },
  {
    // Node-side build tooling, not part of the shipped bundle.
    files: ["scripts/**/*.js", "webpack.config.js"],
    languageOptions: {
      sourceType: "commonjs",
      globals: { ...globals.node },
    },
  },
  {
    files: ["**/*.css"],
    plugins: {
      css,
    },
    language: "css/css",
    rules: {
      "css/no-duplicate-imports": "error",
    },
  },
]);

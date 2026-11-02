/**
 * @file
 * CKEditor 5 plugin adding OIT color and background options to icons.
 *
 * Extends the contrib ckeditor5_icons "Icon" plugin with two extra dropdowns
 * in the icon widget toolbar: icon color and background shape. Based on the
 * CU Boulder "cuiconextras" plugin (GPL-2.0) from ucb_ckeditor_plugins.
 *
 * Written as plain JavaScript against the CKEditor 5 DLL shipped with Drupal
 * core, so no build step is needed. Drupal resolves the plugin from the
 * global as CKEditor5.oitIconExtras.OitIconExtras.
 *
 * Model:
 *   <icon iconOitColor="..." iconOitBackgroundStyle="..."></icon>
 * HTML:
 *   <i class="oit-icon-color-... oit-icon-style-..."></i>
 */

((CKEditor5) => {
  const { Plugin, Command } = CKEditor5.dll('./src/core.js');
  const { ButtonView, createDropdown, addToolbarToDropdown } =
    CKEditor5.dll('./src/ui.js');
  const { Widget, WidgetToolbarRepository } = CKEditor5.dll('./src/widget.js');

  // === Icons ===
  const swatch = (fill) =>
    `<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><rect x="2" y="2" width="16" height="16" rx="3" fill="${fill}" stroke="#888" stroke-width="1"/></svg>`;
  const swatchNone =
    '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><rect x="2" y="2" width="16" height="16" rx="3" fill="#fff" stroke="#888" stroke-width="1"/><path d="M4 16 16 4" stroke="#8a3131" stroke-width="1.5"/></svg>';
  // Font Awesome Free 6.4.0 palette icon (CC BY 4.0).
  const themeIcon =
    '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="m18 10v0.08437c-0.012504 1.1406-1.05 1.9156-2.1906 1.9156h-3.0594c-0.82812 0-1.5 0.67188-1.5 1.5 0 0.10625 0.0125 0.20938 0.03125 0.30938 0.06562 0.31875 0.20312 0.625 0.3375 0.93438 0.19062 0.43125 0.37812 0.85938 0.37812 1.3125 0 0.99375-0.675 1.8969-1.6688 1.9375-0.10938 0.003125-0.21875 0.00625-0.33125 0.00625-4.4156 0-7.9969-3.5812-7.9969-8s3.5812-8 8-8 8 3.5812 8 8zm-12 1a1 1 0 1 0-2 0 1 1 0 1 0 2 0zm0-3a1 1 0 1 0 0-2 1 1 0 1 0 0 2zm5-3a1 1 0 1 0-2 0 1 1 0 1 0 2 0zm3 3a1 1 0 1 0 0-2 1 1 0 1 0 0 2z"/></svg>';
  const rook =
    'm5.28 15.095 0.97-0.97h7.5l0.97 0.97c0.099 0.099 0.155 0.235 0.155 0.375 0 0.293-0.237 0.53-0.53 0.53h-8.69c-0.293 0-0.53-0.237-0.53-0.53 0-0.14 0.056-0.276 0.155-0.375zm0.22-6.595v-3.375c0-0.206 0.169-0.375 0.375-0.375h1.5c0.206 0 0.375 0.169 0.375 0.375v0.938c0 0.103 0.084 0.187 0.188 0.187h0.75c0.103 0 0.187-0.084 0.187-0.187v-0.938c0-0.206 0.169-0.375 0.375-0.375h1.5c0.206 0 0.375 0.169 0.375 0.375v0.938c0 0.103 0.084 0.187 0.188 0.187h0.75c0.103 0 0.187-0.084 0.187-0.187v-0.938c0-0.206 0.169-0.375 0.375-0.375h1.5c0.206 0 0.375 0.169 0.375 0.375v3.375c0 0.237-0.11 0.459-0.3 0.6l-1.2 0.9 0.375 3.375h-6.75l0.375-3.375-1.2-0.9c-0.19-0.141-0.3-0.363-0.3-0.6zm4.125 2.25h0.75c0.206 0 0.375-0.169 0.375-0.375v-1.125c0-0.415-0.335-0.75-0.75-0.75s-0.75 0.335-0.75 0.75v1.125c0 0.206 0.169 0.375 0.375 0.375z';
  const shapeIcon = (outline) =>
    `<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="${outline}${rook}"/></svg>`;

  // === Options ===
  // Colors match the dingo theme palette
  // (components/00-base/01-colors/_00-colors-vars.scss).
  const colorOptions = {
    none: { label: 'Same as text', icon: swatchNone },
    gold: { label: 'Gold', icon: swatch('#cfb87c'), className: 'oit-icon-color-gold' },
    blue: { label: 'Blue', icon: swatch('#0277bd'), className: 'oit-icon-color-blue' },
    green: { label: 'Green', icon: swatch('#26874a'), className: 'oit-icon-color-green' },
    red: { label: 'Red', icon: swatch('#8a3131'), className: 'oit-icon-color-red' },
    black: { label: 'Black', icon: swatch('#000'), className: 'oit-icon-color-black' },
    darkGray: { label: 'Dark Gray', icon: swatch('#424242'), className: 'oit-icon-color-dark-gray' },
    gray: { label: 'Gray', icon: swatch('#666'), className: 'oit-icon-color-gray' },
    lightGray: { label: 'Light Gray', icon: swatch('#ccc'), className: 'oit-icon-color-light-gray' },
    white: { label: 'White', icon: swatch('#fff'), className: 'oit-icon-color-white' },
  };
  const colorDefault = 'none';

  const backgroundStyleOptions = {
    none: { label: 'No background', icon: shapeIcon('') },
    square: {
      label: 'Square',
      icon: shapeIcon('m19 1h-18v18h18v-18z'),
      className: 'oit-icon-style-square',
    },
    squareRounded: {
      label: 'Rounded square',
      icon: shapeIcon(
        'm19 5.5c0-2.484-2.016-4.5-4.5-4.5h-9c-2.484 0-4.5 2.016-4.5 4.5v9c0 2.484 2.016 4.5 4.5 4.5h9c2.484 0 4.5-2.016 4.5-4.5v-9z',
      ),
      className: 'oit-icon-style-square-rounded',
    },
    circle: {
      label: 'Circle',
      icon: shapeIcon('m10 1a9 9 0 1 0 0 18 9 9 0 1 0 0-18z'),
      className: 'oit-icon-style-circle',
    },
  };
  const backgroundStyleDefault = 'none';

  /**
   * Returns the selected icon model element, or null.
   */
  function getSelectedIconWidget(selection) {
    const element = selection.getSelectedElement();
    return element && element.is('element') && element.name === 'icon'
      ? element
      : null;
  }

  /**
   * Builds an attributeToAttribute definition mapping values to classes.
   */
  function classDefinition(attributeName, options) {
    const view = {};
    const values = [];
    Object.entries(options).forEach(([value, option]) => {
      if (!option.className) {
        return;
      }
      values.push(value);
      view[value] = { key: 'class', value: option.className };
    });
    return { model: { key: attributeName, values }, view };
  }

  /**
   * Command that sets an attribute on the selected icon.
   */
  class ModifyIconCommand extends Command {
    constructor(editor, attributeName, defaultValue) {
      super(editor);
      this.attributeName = attributeName;
      this.defaultValue = defaultValue;
      this.value = defaultValue;
      this.iconWidget = null;
    }

    refresh() {
      this.iconWidget = getSelectedIconWidget(
        this.editor.model.document.selection,
      );
      this.isEnabled = !!this.iconWidget;
      this.value =
        this.iconWidget && this.iconWidget.hasAttribute(this.attributeName)
          ? this.iconWidget.getAttribute(this.attributeName)
          : this.defaultValue;
    }

    execute({ value } = {}) {
      const iconWidget = this.iconWidget;
      if (!iconWidget) {
        return;
      }
      this.editor.model.change((writer) => {
        const newValue = value || this.defaultValue;
        if (newValue === this.defaultValue) {
          writer.removeAttribute(this.attributeName, iconWidget);
        } else {
          writer.setAttribute(this.attributeName, newValue, iconWidget);
        }
      });
    }
  }

  /**
   * Schema, converters and commands.
   */
  class OitIconExtrasEditing extends Plugin {
    static get pluginName() {
      return 'OitIconExtrasEditing';
    }

    static get requires() {
      return [Widget];
    }

    init() {
      const { conversion, commands } = this.editor;
      conversion.attributeToAttribute(
        classDefinition('iconOitColor', colorOptions),
      );
      conversion.attributeToAttribute(
        classDefinition('iconOitBackgroundStyle', backgroundStyleOptions),
      );
      commands.add(
        'changeIconOitColor',
        new ModifyIconCommand(this.editor, 'iconOitColor', colorDefault),
      );
      commands.add(
        'changeIconOitBackgroundStyle',
        new ModifyIconCommand(
          this.editor,
          'iconOitBackgroundStyle',
          backgroundStyleDefault,
        ),
      );
    }

    afterInit() {
      // The "icon" model element is registered by the contrib IconEditing
      // plugin's init(), so extend it here when it is guaranteed to exist.
      this.editor.model.schema.extend('icon', {
        allowAttributes: ['iconOitColor', 'iconOitBackgroundStyle'],
      });
    }
  }

  /**
   * Creates a toolbar button that executes a command with a value.
   */
  function createToolbarButton(locale, option, command, value) {
    const editor = command.editor;
    const button = new ButtonView(locale);
    button.set({
      label: locale.t(option.label),
      icon: option.icon,
      tooltip: !!option.icon,
      withText: !option.icon,
      isToggleable: true,
    });
    button.bind('isEnabled').to(command);
    button
      .bind('isOn')
      .to(command, 'value', (commandValue) => commandValue === value);
    button.on('execute', () => {
      command.execute({ value });
      editor.editing.view.focus();
    });
    return button;
  }

  /**
   * Creates a dropdown containing a toolbar of option buttons.
   *
   * When followSelection is set, the dropdown button's icon and label update
   * to reflect the current value.
   */
  function createToolbarDropdown(
    locale,
    label,
    icon,
    command,
    options,
    followSelection,
  ) {
    const t = locale.t;
    const dropdownView = createDropdown(locale);
    const buttonView = dropdownView.buttonView;
    addToolbarToDropdown(
      dropdownView,
      Object.entries(options).map(([value, option]) =>
        createToolbarButton(locale, option, command, value),
      ),
    );
    buttonView.set({
      label: t(label),
      icon,
      tooltip: t(label),
      withText: !icon,
      class: 'ck-dropdown__button_label-width_auto',
    });
    if (followSelection) {
      command.on('change:value', (eventInfo, name, value) => {
        const option = options[value] || options[command.defaultValue];
        buttonView.set({
          label: t(option.label),
          icon: option.icon,
          withText: !option.icon,
        });
      });
    }
    dropdownView.bind('isEnabled').to(command, 'isEnabled');
    return dropdownView;
  }

  /**
   * Registers the toolbar dropdowns and adds them to the icon widget toolbar.
   */
  class OitIconExtrasToolbar extends Plugin {
    static get pluginName() {
      return 'OitIconExtrasToolbar';
    }

    static get requires() {
      return [WidgetToolbarRepository];
    }

    init() {
      const editor = this.editor;
      const { commands } = editor;
      const componentFactory = editor.ui.componentFactory;

      componentFactory.add('iconOitColor', (locale) =>
        createToolbarDropdown(
          locale,
          'Icon color',
          themeIcon,
          commands.get('changeIconOitColor'),
          colorOptions,
          false,
        ),
      );
      componentFactory.add('iconOitBackgroundStyle', (locale) =>
        createToolbarDropdown(
          locale,
          'Icon background',
          backgroundStyleOptions[backgroundStyleDefault].icon,
          commands.get('changeIconOitBackgroundStyle'),
          backgroundStyleOptions,
          true,
        ),
      );

      // The contrib IconToolbar reads this config in afterInit(), so append
      // our items to whatever is configured rather than replacing them.
      const items = editor.config.get('icon.toolbarItems') || [
        'iconSize',
        'iconAlignment',
        'iconStyle',
      ];
      editor.config.set('icon.toolbarItems', [
        ...items.filter(
          (item) => item !== 'iconOitColor' && item !== 'iconOitBackgroundStyle',
        ),
        'iconOitColor',
        'iconOitBackgroundStyle',
      ]);
    }
  }

  /**
   * Glue plugin loading the editing and toolbar parts.
   */
  class OitIconExtras extends Plugin {
    static get pluginName() {
      return 'OitIconExtras';
    }

    static get requires() {
      return [OitIconExtrasEditing, OitIconExtrasToolbar];
    }
  }

  CKEditor5.oitIconExtras = { OitIconExtras };
})(CKEditor5);

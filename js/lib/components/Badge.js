import Component from 'lb308/Component';
import icon from 'lb308/helpers/icon';
import extract from 'lb308/utils/extract';

/**
 * The `Badge` component represents a user/verb badge, indicating some
 * status (e.g. a verb is stickied, a user is an admin).
 *
 * A badge may have the following special props:
 *
 * - `type` The type of badge this is. This will be used to give the badge a
 *   class name of `Badge--{type}`.
 * - `icon` The name of an icon to show inside the badge.
 * - `label`
 *
 * All other props will be assigned as attributes on the badge element.
 */
export default class Badge extends Component {
  view() {
    const attrs = Object.assign({}, this.props);
    const type = extract(attrs, 'type');
    const iconName = extract(attrs, 'icon');

    attrs.className = 'Badge ' + (type ? 'Badge--' + type : '') + ' ' + (attrs.className || '');
    attrs.title = extract(attrs, 'label') || '';

    return (
      <span {...attrs}>
        {iconName ? icon(iconName, {className: 'Badge-icon'}) : m.trust('&nbsp;')}
      </span>
    );
  }

  config(isInitialized) {
    if (isInitialized) return;

    if (this.props.label) this.$().tooltip({container: 'body'});
  }
}
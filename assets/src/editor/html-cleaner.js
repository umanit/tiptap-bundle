/**
 * HtmlCleaner
 */
const HtmlCleaner = {
  FORBIDDEN_CLASSES: [
    /^Mso/, // Word Desktop
    /^SCX/, // Word Online
    /^kix-/, // Google Docs
    /^[a-z0-9]{20,}$/, // Hashs/Noise
  ],

  normalizeWordHtml(html) {
    if (!html) {
      return html;
    }

    if (
      !html.includes('SCXW')
      && !html.includes('Mso')
      && !html.includes('margin')
      && !html.includes('<style>')
    ) {
      return html;
    }

    try {
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');

      const elements = doc.querySelectorAll('p, div, span');

      elements.forEach((el) => {
        const style = el.getAttribute('style');
        if (!style) {
          return;
        }

        let leftMargin = 0;

        const mlMatch = style.match(/margin-left\s*:\s*([0-9.]+)px/i);
        if (mlMatch) {
          leftMargin = parseFloat(mlMatch[1]);
        }

        if (leftMargin === 0) {
          const shorthandMatch = style.match(
            /margin\s*:\s*[0-9.a-z]+\s+[0-9.a-z]+\s+[0-9.a-z]+\s+([0-9.]+)px/i,
          );
          if (shorthandMatch) {
            leftMargin = parseFloat(shorthandMatch[1]);
          }
        }

        if (leftMargin > 250) {
          el.style.textAlign = 'right';
          el.style.marginLeft = '0px';
          el.style.margin = '0px';
        }
      });

      const styleMatch = html.match(/<style[^>]*>([\s\S]*?)<\/style>/i);

      if (styleMatch && styleMatch[1]) {
        const cssContent = styleMatch[1];
        const ruleRegex = /\.([a-zA-Z0-9_-]+)[^}]*text-align\s*:\s*(left|right|center|justify)/gi;

        let match = ruleRegex.exec(cssContent);
        while (match !== null) {
          const className = match[1];
          const alignment = match[2];

          try {
            const targets = doc.querySelectorAll(`.${className}`);
            targets.forEach((el) => {
              if (!el.style.textAlign) {
                el.style.textAlign = alignment;
              }
            });
          } catch (e) {
            console.warn(`HtmlCleaner: Cannot apply the style of the class ".${className}"`, e);
          }

          match = ruleRegex.exec(cssContent);
        }
      }

      return doc.body.innerHTML;
    } catch {
      return html;
    }
  },

  parseClass(element) {
    const classString = element.getAttribute('class');
    if (!classString) {
      return null;
    }

    const classes = classString.split(/\s+/);
    const validClasses = classes.filter(
      (cls) => !this.FORBIDDEN_CLASSES.some((pattern) => pattern.test(cls)),
    );

    return validClasses.length > 0 ? validClasses.join(' ') : null;
  },
};

export default HtmlCleaner;

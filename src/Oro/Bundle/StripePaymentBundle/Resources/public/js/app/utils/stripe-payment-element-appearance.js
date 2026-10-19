const THEME_APPEARANCE_CSS_VARIABLES = {
    colorDanger: '--stripe-payment-element-error-color',
    fontSizeSm: '--stripe-payment-element-font-size-sm',
    errorMarginTop: '--stripe-payment-element-error-margin-top'
};

export default function mergeThemeAppearanceStyles(appearance, paymentContainer) {
    const containerStyle = getComputedStyle(paymentContainer);
    const readCssVariable = variableName => containerStyle.getPropertyValue(variableName).trim();

    return {
        ...appearance,
        variables: {
            colorDanger: readCssVariable(THEME_APPEARANCE_CSS_VARIABLES.colorDanger),
            fontSizeSm: readCssVariable(THEME_APPEARANCE_CSS_VARIABLES.fontSizeSm),
            ...appearance.variables
        },
        rules: {
            '.Error': {
                marginTop: readCssVariable(THEME_APPEARANCE_CSS_VARIABLES.errorMarginTop)
            },
            ...appearance.rules
        }
    };
}

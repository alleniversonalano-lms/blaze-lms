/* Formula evaluation functions */
const formulaHandling = {
    evaluateFormula: function(formula, variables) {
        // Split formula into steps
        const steps = formula.split(';').map(step => step.trim()).filter(s => s);
        const tempVars = { ...variables };
        const calculations = [];
        let finalResult;

        // Process each step
        for (const step of steps) {
            try {
                if (step.includes('=')) {
                    // Variable definition
                    const [varName, expression] = step.split('=').map(s => s.trim());
                    const processedExpr = this.formatExpression(expression);
                    
                    // Calculate the value using the expression
                    const jsExpr = this.convertToJs(expression);
                    const context = { Math, ...tempVars };
                    const safeEval = new Function(...Object.keys(context), `return ${jsExpr}`);
                    const result = safeEval(...Object.values(context));
                    
                    tempVars[varName] = result;
                    calculations.push({
                        type: 'definition',
                        varName,
                        expression: processedExpr,
                        result: Number(result.toFixed(3))
                    });
                } else {
                    // Final calculation
                    const processedExpr = this.formatExpression(step);
                    const jsExpr = this.convertToJs(step);
                    const context = { Math, ...tempVars };
                    const safeEval = new Function(...Object.keys(context), `return ${jsExpr}`);
                    finalResult = safeEval(...Object.values(context));
                    
                    calculations.push({
                        type: 'calculation',
                        expression: processedExpr,
                        result: Number(finalResult.toFixed(3))
                    });
                }
            } catch (error) {
                throw new Error(`Error in step "${step}": ${error.message}`);
            }
        }

        return {
            steps: calculations,
            result: finalResult,
            variables: tempVars
        };
    },

    formatExpression: function(expr) {
        return expr
            .replace(/\*/g, '×')
            .replace(/\//g, '÷')
            .replace(/\^/g, '^')
            .replace(/pi/g, 'π')
            .replace(/sqrt\(/g, '√(')
            .replace(/cbrt\(/g, '∛(')
            .replace(/asin/g, 'sin⁻¹')
            .replace(/acos/g, 'cos⁻¹')
            .replace(/atan/g, 'tan⁻¹')
            .replace(/asinh/g, 'sinh⁻¹')
            .replace(/acosh/g, 'cosh⁻¹')
            .replace(/atanh/g, 'tanh⁻¹')
            .replace(/log2\(/g, 'log₂(')
            .replace(/exp\(/g, 'e^')
            .replace(/pow\(([^,]+),([^)]+)\)/g, '$1^$2');
    },

    convertToJs: function(expr) {
        return expr
            .replace(/\^/g, '**')
            .replace(/pi/g, 'Math.PI')
            .replace(/e(?!\w)/g, 'Math.E')
            .replace(/sin\(/g, 'Math.sin(')
            .replace(/cos\(/g, 'Math.cos(')
            .replace(/tan\(/g, 'Math.tan(')
            .replace(/asin\(/g, 'Math.asin(')
            .replace(/acos\(/g, 'Math.acos(')
            .replace(/atan\(/g, 'Math.atan(')
            .replace(/sinh\(/g, 'Math.sinh(')
            .replace(/cosh\(/g, 'Math.cosh(')
            .replace(/tanh\(/g, 'Math.tanh(')
            .replace(/asinh\(/g, 'Math.asinh(')
            .replace(/acosh\(/g, 'Math.acosh(')
            .replace(/atanh\(/g, 'Math.atanh(')
            .replace(/log\(/g, 'Math.log10(')
            .replace(/ln\(/g, 'Math.log(')
            .replace(/log2\(/g, 'Math.log2(')
            .replace(/sqrt\(/g, 'Math.sqrt(')
            .replace(/cbrt\(/g, 'Math.cbrt(')
            .replace(/exp\(/g, 'Math.exp(')
            .replace(/pow\(/g, 'Math.pow(')
            .replace(/floor\(/g, 'Math.floor(')
            .replace(/ceil\(/g, 'Math.ceil(')
            .replace(/round\(/g, 'Math.round(')
            .replace(/abs\(/g, 'Math.abs(')
            .replace(/sign\(/g, 'Math.sign(');
    }
};

function generateVariableValues(variables) {
    const values = {};
    
    // First generate values for base variables
    Object.entries(variables).forEach(([name, variable]) => {
        if (!variable.isCalculated) {
            const min = parseFloat(variable.min) || 0;
            const max = parseFloat(variable.max) || 10;
            const step = parseFloat(variable.step) || 1;
            const decimals = parseInt(variable.decimals) || 0;
            
            const range = max - min;
            const validStep = Math.min(step, range);
            const possibleSteps = Math.floor(range / validStep);
            const randomStepCount = Math.floor(Math.random() * possibleSteps);
            const value = Number((min + (randomStepCount * validStep)).toFixed(decimals));
            
            values[name] = value;
        }
    });

    return values;
}

function updateFormulaPreview(questionId, formulaIndex) {
    const question = questions.find(q => q.id === questionId);
    const preview = document.getElementById(`formulaPreview_${questionId}_${formulaIndex}`);

    if (!preview) return;

    const formulaData = question.formulas[formulaIndex];
    if (!formulaData?.formula) {
        preview.innerHTML = '<div class="text-muted text-center p-3">Enter a formula to see the preview</div>';
        return;
    }

    if (!question.variables || Object.keys(question.variables).length === 0) {
        preview.innerHTML = '<div class="text-muted text-center p-3">Add variables to preview the calculation</div>';
        return;
    }

    try {
        // Generate sample values for variables
        const values = generateVariableValues(question.variables);

        // Evaluate the formula
        const evaluation = formulaHandling.evaluateFormula(formulaData.formula, values);

        // Create HTML for each step
        const stepsHTML = evaluation.steps.map((step, index) => {
            if (step.type === 'definition') {
                return `
                    <div class="step">
                        ${index + 1}. Calculate ${step.varName}:
                        <div class="formula-step">
                            ${step.varName} = ${step.expression}
                            = ${step.result}
                        </div>
                    </div>
                `;
            } else {
                return `
                    <div class="step">
                        ${index + 1}. Calculate final result:
                        <div class="formula-step">
                            ${step.expression}
                            = ${step.result}
                        </div>
                    </div>
                `;
            }
        }).join('');

        // Update the preview
        preview.innerHTML = `
            <div class="formula-preview">
                <div class="preview-section">
                    <div class="preview-label">Formula Steps:</div>
                    <div class="preview-content formula-display">
                        ${formulaData.formula.split(';').map(step => step.trim()).join('<br>')}
                    </div>
                </div>

                <div class="preview-section">
                    <div class="preview-label">Given Values:</div>
                    <div class="preview-content preview-values">
                        ${Object.entries(values)
                            .map(([name, value]) => 
                                `<div class="value-item">
                                    ${name} = ${value}
                                </div>`
                            ).join('')}
                    </div>
                </div>

                <div class="preview-section">
                    <div class="preview-label">Solution:</div>
                    <div class="preview-content">
                        ${stepsHTML}
                        <div class="step result-final">
                            Final Answer = ${evaluation.result}
                        </div>
                    </div>
                </div>
            </div>

            <style>
                .formula-preview {
                    background: #f8f9fa;
                    border: 1px solid #dee2e6;
                    border-radius: 6px;
                    padding: 16px;
                }
                .preview-section {
                    margin-bottom: 16px;
                }
                .preview-section:last-child {
                    margin-bottom: 0;
                }
                .preview-label {
                    font-weight: 600;
                    color: #495057;
                    margin-bottom: 8px;
                }
                .preview-content {
                    background: white;
                    padding: 12px;
                    border-radius: 4px;
                    border: 1px solid #e9ecef;
                }
                .formula-display {
                    font-family: "Computer Modern", monospace;
                    font-size: 1.1em;
                    line-height: 1.5;
                }
                .preview-values {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                    gap: 8px;
                }
                .value-item {
                    padding: 4px 8px;
                    background: #f8f9fa;
                    border-radius: 4px;
                    font-family: monospace;
                }
                .step {
                    margin-bottom: 12px;
                }
                .formula-step {
                    font-family: monospace;
                    margin-top: 4px;
                    padding: 8px 12px;
                    background: #f8f9fa;
                    border-radius: 4px;
                    line-height: 1.5;
                }
                .result-final {
                    color: #0374b5;
                    font-weight: 600;
                    font-size: 1.1em;
                }
            </style>
        `;
    } catch (error) {
        preview.innerHTML = `
            <div style="color: #dc3545; padding: 12px; background: #fff5f5; border-radius: 4px; border: 1px solid #dc3545;">
                <strong>Error:</strong> ${error.message}
            </div>
        `;
    }
}

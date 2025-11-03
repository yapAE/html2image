package converter

// Factory creates converters
type Factory struct{}

// NewFactory creates a new Factory
func NewFactory() *Factory {
	return &Factory{}
}

// CreateConverter creates a converter based on the name
func (f *Factory) CreateConverter(name string) Converter {
	switch name {
	case "plutobook":
		return NewPlutoBookConverter()
	case "wkhtmltox":
		fallthrough
	default:
		return NewWkhtmltoxConverter()
	}
}

// CreateDefaultConverter creates the default converter (plutobook)
func (f *Factory) CreateDefaultConverter() Converter {
	return f.CreateConverter("plutobook")
}

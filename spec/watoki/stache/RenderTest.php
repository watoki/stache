<?php
namespace spec\watoki\stache;

use watoki\collections\Liste;
use watoki\collections\Map;
use watoki\stache\ParsingException;
use watoki\stache\Renderer;

class TemplateTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var object
     */
    public $view;

    /**
     * @var string
     */
    public $output;

    /**
     * @var \Exception
     */
    public $exception;

    /**
     * @var string
     */
    public $template;

    private $innerView;

    public function testNonExistingProperty() {
        $this->givenATemplate('Hello{{nothing}}!');
        $this->givenAViewObject();

        $this->whenTheTemplateIsRenderedWithThisView();

        $this->thenTheOutputShouldBe('Hello!');
    }

    public function testParseSimpleTag() {
        $this->givenATemplate('Hello {{myTag}}! Hello {{myTag}}!');
        $this->givenAViewObject();
        $this->givenTheViewObjectContainsTheProperty_WithValue('myTag', 'World');

        $this->whenTheTemplateIsRenderedWithThisView();

        $this->thenTheOutputShouldBe('Hello World! Hello World!');
    }

    public function testReplaceWithZero() {
        $this->givenATemplate('Zero is {{myTag}}');
        $this->givenAViewObject();
        $this->givenTheViewObjectContainsTheProperty_WithValue('myTag', 0);

        $this->whenTheTemplateIsRenderedWithThisView();

        $this->thenTheOutputShouldBe('Zero is 0');
    }

    public function testParseMethodTag() {
        $this->givenATemplate('Hello {{myMethod}}!');
        $this->givenAViewObject();
        $this->givenTheViewObjectsTheMethodMyMethodReturns('World');

        $this->whenTheTemplateIsRenderedWithThisView();

        $this->thenTheOutputShouldBe('Hello World!');
    }

    public function testObjectToString() {
        $class = new \ReflectionClass(get_class($this));

        $this->givenATemplate("It's {{myDate}}");
        $this->givenAViewObject();
        $this->givenTheViewObjectContainsTheProperty_WithValue('myDate', $class);

        $this->whenTheTemplateIsRenderedWithThisView();

        $this->thenTheOutputShouldBe("It's " . $class->__toString());
    }

    public function testFailIfBlockIsNotClosed() {
        $this->givenATemplate('Hello {{#myMethod}}!');
        $this->givenAViewObject();

        $this->whenTheTemplateIsRenderedWithThisView();

        $this->thenAParsingErrorShouldBeThrown();
    }

    public function testParseMethodBlockWithInput() {
        $this->givenATemplate('Hello {{#exclaim}}World{{/exclaim}}');
        $this->givenAViewObject();
        $this->givenTheViewObjectsHasAMethodExclaimAdding_ToItsInput('!');

        $this->whenTheTemplateIsRenderedWithThisView();

        $this->thenTheOutputShouldBe('Hello World!');
    }

    public function testParseTwoMethodBlocksWithInput() {
        $this->givenATemplate(
            'Hello {{#exclaim}}World{{/exclaim}} Hello {{#exclaim}}Derp{{/exclaim}}'
        );
        $this->givenAViewObject();
        $this->givenTheViewObjectsHasAMethodExclaimAdding_ToItsInput('!');

        $this->whenTheTemplateIsRenderedWithThisView();

        $this->thenTheOutputShouldBe('Hello World! Hello Derp!');
    }

    public function testParseStackedMethodBlocksWithInput() {
        $this->givenATemplate(
            'Hello {{#exclaim}}World, Hello {{#exclaim}}Derp{{/exclaim}}{{/exclaim}}'
        );
        $this->givenAViewObject();
        $this->givenTheViewObjectsHasAMethodExclaimAdding_ToItsInput('!');

        $this->whenTheTemplateIsRenderedWithThisView();

        $this->thenTheOutputShouldBe('Hello World, Hello Derp!!');
    }

    public function testBlockOfUndefinedProperty() {
        $this->givenATemplate('Hello{{#notDefined}} World{{/notDefined}}!');
        $this->givenAViewObject();

        $this->whenTheTemplateIsRenderedWithThisView();

        $this->thenTheOutputShouldBe('Hello!');
    }

    public function testTrueBlock() {
        $this->givenATemplate('Hello{{#myTag}} World{{/myTag}}!');
        $this->givenAViewObject();
        $this->givenTheViewObjectContainsTheProperty_WithValue('myTag', true);

        $this->whenTheTemplateIsRenderedWithThisView();

        $this->thenTheOutputShouldBe('Hello World!');
    }

    public function testElseBlock() {
        $this->givenATemplate(
            'Hello{{#myTag}} {{#exclaim}}World{{/exclaim}}{{/myTag}}{{^myTag}} {{#exclaim}}Derp{{/exclaim}}{{/myTag}}'
        );
        $this->givenAViewObject();
        $this->givenTheViewObjectsHasAMethodExclaimAdding_ToItsInput('!');

        $this->givenTheViewObjectContainsTheProperty_WithValue('myTag', true);
        $this->whenTheTemplateIsRenderedWithThisView();

        $this->thenTheOutputShouldBe('Hello World!');

        $this->givenTheViewObjectContainsTheProperty_WithValue('myTag', false);
        $this->whenTheTemplateIsRenderedWithThisView();

        $this->thenTheOutputShouldBe('Hello Derp!');
    }

    public function testFalseBlock() {
        $this->givenATemplate('Hello{{#myTag}} World{{/myTag}}!');
        $this->givenAViewObject();
        $this->givenTheViewObjectContainsTheProperty_WithValue('myTag', false);

        $this->whenTheTemplateIsRenderedWithThisView();

        $this->thenTheOutputShouldBe('Hello!');
    }

    public function testArrayBlock() {
        $this->givenATemplate('Hello{{#myTag}} {{foo}}{{#isLast}}!{{/isLast}}{{/myTag}}');
        $this->givenAViewObject();
        $this->givenTheViewObjectContainsTheProperty_WithValue(
            'myTag',
            array(
                array('foo' => 'beautiful'),
                array('foo' => 'World', 'isLast' => true)
            )
        );

        $this->whenTheTemplateIsRenderedWithThisView();

        $this->thenTheOutputShouldBe('Hello beautiful World!');
    }

    public function testArrayBlockWithListtAndMap() {
        $this->givenATemplate('Hello{{#myTag}} {{foo}}{{#isLast}}!{{/isLast}}{{/myTag}}');
        $this->givenAViewObject();

        $myTag = new Liste();
        $myTag->append(new Map(array('foo' => 'Hi')));
        $myTag->append(new Map(array('foo' => 'You', 'isLast' => true)));

        $this->givenTheViewObjectContainsTheProperty_WithValue('myTag', $myTag);

        $this->whenTheTemplateIsRenderedWithThisView();

        $this->thenTheOutputShouldBe('Hello Hi You!');
    }

    public function testSelfReference() {
        $this->givenATemplate('{{#list}}{{this}}{{/list}}');
        $this->givenAViewObject();
        $this->givenTheViewObjectContainsTheProperty_WithValue(
            'list',
            array('One', 'Two', 'Three')
        );

        $this->whenTheTemplateIsRenderedWithThisView();

        $this->thenTheOutputShouldBe('OneTwoThree');

    }

    public function testCallBackBlock() {
        $this->givenATemplate('Hello {{#small}}World{{/small}}!');
        $this->givenAViewObject();
        $this->givenTheViewHasTheCallbackProperty_WhichLowercasesItsInput('small');

        $this->whenTheTemplateIsRenderedWithThisView();

        $this->thenTheOutputShouldBe('Hello world!');
    }

    public function testViewBlock() {
        $this->givenATemplate('Hello{{#inner}} {{#big}}World{{/big}}{{/inner}}!');
        $this->givenAViewObject();
        $this->givenTheViewObjectContainsTheProperty_WhichIsAnInnerView('inner');
        $this->givenTheInnerViewHasTheCallbackProperty_WhichCapitalizesItsInput('big');

        $this->whenTheTemplateIsRenderedWithThisView();

        $this->thenTheOutputShouldBe('Hello WORLD!');
    }

    public function testTwoViewBlocks() {
        $this->givenATemplate('{{#inner}}{{#big}}Hello{{/big}}{{/inner}} {{#inner}}{{#big}}World{{/big}}{{/inner}}!');
        $this->givenAViewObject();
        $this->givenTheViewObjectContainsTheProperty_WhichIsAnInnerView('inner');
        $this->givenTheInnerViewHasTheCallbackProperty_WhichCapitalizesItsInput('big');

        $this->whenTheTemplateIsRenderedWithThisView();

        $this->thenTheOutputShouldBe('HELLO WORLD!');
    }

    public function testViewBlockWithScopes() {
        $this->givenATemplate('{{#inner}}{{#big}}Hello{{/big}}{{/inner}} {{#inner}}{{#parent}}{{myTag}}{{/parent}}{{/inner}}!');
        $this->givenAViewObject();
        $this->givenTheViewObjectContainsTheProperty_WithValue('myTag', 'World');
        $this->givenTheViewObjectContainsTheProperty_WhichIsAnInnerView('inner');
        $this->givenTheInnerViewHasTheCallbackProperty_WhichCapitalizesItsInput('big');

        $this->whenTheTemplateIsRenderedWithThisView();

        $this->thenTheOutputShouldBe('HELLO World!');
    }

    public function testMethodBeforeProperty() {
        $this->givenATemplate('Hello {{myMethod}}!');
        $this->givenAViewObject();
        $this->givenTheViewObjectContainsTheProperty_WithValue('myMethod', 'You');
        $this->givenTheViewObjectsTheMethodMyMethodReturns('World');

        $this->whenTheTemplateIsRenderedWithThisView();

        $this->thenTheOutputShouldBe('Hello World!');
    }

    private function givenATemplate($content) {
        $this->template = $content;
    }

    private function givenAViewObject() {
        $this->view = new TemplateTest_View();
    }

    private function givenTheViewObjectContainsTheProperty_WithValue($name, $value) {
        $this->view->$name = $value;
    }

    private function whenTheTemplateIsRenderedWithThisView() {
        $template = new Renderer($this->template);
        try {
            $this->output = $template->render($this->view);
        } catch (\Exception $e) {
            $this->exception = $e;
        }
    }

    private function thenTheOutputShouldBe($output) {
        $this->assertEquals($output, $this->output);
    }

    private function givenTheViewObjectsTheMethodMyMethodReturns($returnValue) {
        $this->view->returnValue = $returnValue;
    }

    private function givenTheViewObjectsHasAMethodExclaimAdding_ToItsInput($string) {
        $this->view->exclaimAdds = $string;
    }

    private function givenTheViewObjectContainsTheProperty_WhichIsAnInnerView($name) {
        $this->innerView = new TemplateTest_View();
        $this->view->$name = $this->innerView;
    }

    private function givenTheInnerViewHasTheCallbackProperty_WhichCapitalizesItsInput($callback) {
        $this->innerView->$callback = function ($input) {
            return strtoupper($input);
        };
    }

    private function givenTheViewHasTheCallbackProperty_WhichLowercasesItsInput($callback) {
        $this->view->$callback = function ($input) {
            return strtolower($input);
        };
    }

    private function thenAParsingErrorShouldBeThrown() {
        $this->assertInstanceOf(ParsingException::$CLASS, $this->exception);
    }

}

class TemplateTest_View {

    public $returnValue = 'Derp';
    public $exclaimAdds = '?';

    public function myMethod() {
        return $this->returnValue;
    }

    public function exclaim($string) {
        return $string . $this->exclaimAdds;
    }

}

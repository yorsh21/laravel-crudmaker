<?php

namespace Yorsh\CrudMaker;

use Illuminate\Console\Command;

class CrudCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:crud {migration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new CRUD from migration file';

    /**
     * Params form CRUD  process.
     *
     * @var string
     */
    private $datatable_name = '';
    private $migration_name = '';
    private $primary_key = '';
    private $model_name = '';
    private $models_path = '';
    private $controllers_path = '';
    private $views_path = '';
    private $view_directory = '';
    private $migrations_path = '';
    private $singular_variable = "";
    private $plural_variable = "";
    private $route_path = "";

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @param  \App\CrudCommand
     * @return mixed
     */
    public function handle()
    {
        $base_path = base_path();

        $this->migration_name = $this->argument('migration');
        $this->models_path = $base_path . '\\app\\Models';
        $this->controllers_path = $base_path . '\\app\\Http\\Controllers';
        $this->views_path = $base_path . '\\resources\\views';
        $this->migrations_path = $base_path . '\\database\\migrations';
        $this->route_path = $base_path . '\\routes\\web.php';

        $this->getFields();
        $this->getModelName();

        $this->createModel();
        $this->createController();
        $this->createViews();
        $this->addRourtes();
    }


    private function getFields() {
        $list = scandir($this->migrations_path);
        $selected = [];

        foreach ($list as $value) {
            if (strpos($value, $this->migration_name) !== false) {
                array_push($selected, $value);
            }
        }

        if(count($selected) == 0) {
            echo "Migration file not found", PHP_EOL;
            exit;
        }
        elseif(count($selected) == 1) {
            echo "Migration file found: " . $selected[0] . "", PHP_EOL;

            $file_selected = fopen($this->migrations_path . "\\" . $selected[0], "r") or die("Unable to open file $selected[0]!");
            $raw_file = fread($file_selected, filesize($this->migrations_path . "\\" . $selected[0]));
            fclose($file_selected);

            $file_content = explode("\n", str_replace("\r", "", $raw_file));
            $fields = [];

            foreach ($file_content as $line) {
                if (strpos($line, '$table->') !== false) {
                    $step1 = explode('$table->', $line)[1];
                    $step2 = explode("(", $step1);
                    $step3 = explode(")", $step2[1]);

                    if (strpos($step3[0], ',') !== false) {
                        $options = explode(",", str_replace([" ", "'", '"'], "", $step3[0]));
                        $name = array_shift($options);
                    }
                    else {
                        $name = substr($step3[0], 1, -1) === false ? "" : substr($step3[0], 1, -1);
                        $options = [];
                    }

                    if (strpos($line, 'default(') !== false) {
                        $step4 = explode(")", $step2[2])[0];
                        $default = str_replace(["'", '"'], "", $step4);
                    }
                    else {
                        $default = false;
                    }

                    if (strpos($line, 'nullable(') !== false) {
                        $nullable = true;
                    }
                    else {
                        $nullable = false;
                    }

                    if ($step2[0] == "foreign") {
                        $step5 = explode("references(", $line)[1];
                        $step6 = explode(")", $step5)[0];
                        $reference = substr($step6, 1, -1);

                        $step7 = explode("on(", $line)[1];
                        $step8 = explode(")", $step7)[0];
                        $table = substr($step8, 1, -1);

                        for ($i=0; $i < count($fields); $i++) { 
                            if ($fields[$i]["name"] == $name) {
                                $fields[$i]["reference"]  = $reference;
                                $fields[$i]["table"]  = $table;
                            }
                        }
                    }
                    else {
                        if (strpos($step2[0], 'ncrements') !== false) {
                            $this->primary_key = "$name";
                        }

                        array_push($fields, [
                            "type" => $step2[0],
                            "name" => $name,
                            "options" => $options,
                            "default" => $default,
                            "nullable" => $nullable,
                        ]);
                    }
                }
                elseif (strpos($line, 'Schema::create') !== false) {
                    $step1 = explode("Schema::create(", $line)[1];
                    $step2 = explode(",", $step1)[0];
                    $this->datatable_name = substr($step2, 1, -1);
                }
            }

            $this->fields = $fields;
        }
        elseif(count($selected) > 1) {
            echo "Several files have been found:", PHP_EOL;
            foreach ($selected as $select) {
                echo " - " . $select . "", PHP_EOL;
            }
            exit;
        }
        else {
            echo "Internal error", PHP_EOL;
            exit;
        }
    }


    private function createModel() {
        if (!file_exists($this->models_path)) {
            mkdir($this->models_path, 0700);
        }

        $file = fopen(__DIR__.'\\..\\templates\\model.template', "r") or die("Unable to open file model.template!");
        $contents = fread($file, filesize(__DIR__.'\\..\\templates\\model.template'));
        fclose($file);

        $contents = str_replace("MODEL_NAME", $this->model_name, $contents);
        $contents = str_replace("TABLE_NAME", $this->datatable_name, $contents);
        $contents = str_replace("PRIMARY_KEY", $this->primary_key, $contents);
        $contents = str_replace("FIELDS", $this->getListFields(), $contents);
        $contents = str_replace("RELATIONSHIPS", $this->getRelationships(), $contents);

        file_put_contents("$this->models_path\\$this->model_name.php", $contents);     // Save our content to the file.
    }


    private function createController() {
        if (!file_exists($this->controllers_path)) {
            mkdir($this->controllers_path . "\\" . $this->model_name . "Controller", 0700);
        }

        $file = fopen(__DIR__.'\\..\\templates\\controller.template', "r") or die("Unable to open file model.template!");
        $contents = fread($file, filesize(__DIR__.'\\..\\templates\\controller.template'));
        fclose($file);

        $contents = str_replace("MODEL_NAME", $this->model_name, $contents);
        $contents = str_replace("SINGULAR_VARIABLE", $this->singular_variable, $contents);
        $contents = str_replace("PLURAL_VARIABLE", $this->plural_variable, $contents);
        $contents = str_replace("VIEW_DIRECTORY", $this->view_directory, $contents);
        $contents = str_replace("ROUTE_BASE", $this->view_directory, $contents);


        file_put_contents("$this->controllers_path\\$this->model_name" . "Controller.php", $contents);     // Save our content to the file.
    }


    private function createViews() {
        if (!file_exists($this->views_path . "\\" . $this->view_directory)) {
            mkdir($this->views_path . "\\" . $this->view_directory, 0700);
        }

        $file = fopen(__DIR__.'\\..\\templates\\index.view.template', "r") or die("Unable to open file model.template!");
        $contents = fread($file, filesize(__DIR__.'\\..\\templates\\index.view.template'));
        fclose($file);

        $contents = str_replace("MODEL_NAME", $this->model_name, $contents);
        $contents = str_replace("PLURAL_VARIABLE", $this->plural_variable, $contents);
        $contents = str_replace("ROUTE_BASE", $this->view_directory, $contents);
        $contents = str_replace("HEADER_TABLE", $this->getFieldsHeaderTable(), $contents);
        $contents = str_replace("BODY_TABLE", $this->getFieldsBodyTable(), $contents);

        $contents = str_replace("MODEL_NAME", $this->model_name, $contents);


        file_put_contents("$this->views_path\\$this->view_directory\\index.blade.php", $contents);     // Save our content to the file.
    }

    private function addRourtes() {
        $file = fopen($this->route_path, "r") or die("Unable to open file model.template!");
        $contents = fread($file, filesize($this->route_path));
        fclose($file);
        
        if (strpos($contents, "Route::resource('$this->view_directory', '$this->model_name" . "Controller');") === false) {
            $resource = "\n\nRoute::resource('$this->view_directory', '$this->model_name" . "Controller');";

            file_put_contents($this->route_path, $resource, FILE_APPEND);
        }
    }


    private function getModelName() {
        $split = explode("_", $this->datatable_name);
        $capit = array_map(function($item) { 
            return ucfirst($item); 
        }, $split);
        $model_name = join($capit);

        if(substr($model_name, -1) == "s") {
            $this->model_name = substr($model_name, 0, -1);
            $this->singular_variable = substr($model_name, 0, -1);
            $this->plural_variable = $model_name;
            $this->view_directory = str_replace("_", "-", substr($this->datatable_name, 0, -1));
        }
        else {
            $this->model_name = $model_name;
            $this->singular_variable = $model_name;
            $this->plural_variable = $model_name . "s";
            $this->view_directory = str_replace("_", "-", $this->datatable_name);
        }
    }


    private function getListFields() {
        $list = "";

        foreach ($this->fields as $field) {
            if (!empty($field["name"])) {
                $list .= "'" . $field["name"] . "', ";
            }
        }

        return substr($list, 0, -2);
    }


    private function getFieldsHeaderTable() {
        $list = "";
        $append = "</th>\n\t\t\t\t\t\t\t\t\t\t";

        foreach ($this->fields as $field) {
            if (!empty($field["name"])) {
                $list .= "<th>" . $field["name"] . $append;
            }
        }

        return $list;
    }


    private function getFieldsBodyTable() {
        $list = "";
        $append = "\n\t\t\t\t\t\t\t\t\t\t";

        foreach ($this->fields as $field) {
            if (!empty($field["name"])) {
                $list .= '<td>{{ $item->' . $field["name"] . ' }}</td>' . $append;
            }
        }

        return $list;
    }


    private function getRelationships() {
        $text = "";
        $template = "\tpublic function BELONGS_TO(){ \n\t\treturn \$this->belongsTo('App\Models\BELONGS_TO_URL'); \n\t} \n\n";
        // $template = 'public function HAS_MANY(){ \n\t\treturn \$this->belongsTo(\'App\Models\HAS_MANY_URL\'); \n\t} \n\n';

        foreach ($this->fields as $field) {
            if (isset($field["reference"])) {
                $split = explode("_", $field["table"]);
                $capit = array_map(function($item) { 
                    return ucfirst($item); 
                }, $split);
                $name = join($capit);

                $temp = str_replace('BELONGS_TO_URL', $name, $template);
                $temp = str_replace('BELONGS_TO', str_replace("_id", "", $field["name"]), $temp);

                $text .= $temp;
            }
        }

        return $text;
    }

}
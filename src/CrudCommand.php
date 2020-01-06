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
    private $migrations_path = '';

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
     * @param  \App\DripEmailer  $drip
     * @return mixed
     */
    public function handle()
    {
        $base_path = base_path();

        $this->migration_name = $this->argument('migration');
        $this->models_path = $base_path . '\\app\\Models';
        $this->controllers_path = $base_path . '\\app\\Http\\Controllers';
        $this->views_path = $base_path . '\\app\\resources\\views';
        $this->migrations_path = $base_path . '\\database\\migrations';

        $this->getFields();
        $this->getModelName();

        $this->createModel();
        $this->createController();
        $this->createViews();
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
            echo "Migration file not fond", PHP_EOL;
            exit;
        }
        elseif(count($selected) == 1) {
            echo "Migration file fond: " . $selected[0] . "", PHP_EOL;

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
                        if (strpos($step2[0], 'ncrements(') !== false) {
                            $this->primary_key = $name;
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

        exit;
    }

    private function createController() {
        mkdir($this->controllers_path . "/ruta/a/mi/directorio", 0700);

    }

    private function createViews() {
        mkdir($this->views_path . "/ruta/a/mi/directorio", 0700);
    }

    private function getModelName() {
        $split = explode("_", $this->datatable_name);
        $capit = array_map(function($item) { 
            return ucfirst($item); 
        }, $split);
        $model_name = join($capit);

        if(substr($model_name, -1) == "s") {
            $this->model_name = substr($model_name, 0, -1);
        }
        else {
            $this->model_name = $model_name;
        }
    }

    private function getListFields() {
        $list = "";

        foreach ($this->fields as $field) {
            if (!empty($field["name"])) {
                $list .= "'" . $field["name"] . "', ";
            }
        }

        return substr($list, 0, -2)
    }

    private function getRelationships() {
        $text = "";
        $template = "public function BELONGS_TO(){ \n\t\treturn $this->belongsTo('App\Models\BELONGS_TO_URL'); \n\t} \n\n";

        foreach ($this->fields as $field) {
            if (isset($field["reference"])) {
                $temp = str_replace(BELONGS_TO, replace, $template); 
            }
        }
    }

}
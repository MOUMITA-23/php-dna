<?php
declare(strict_types=1);
namespace Php8\Migration;
use Exception;
use InvalidArgumentException;

class VariadicInherit
{
    const ERR_MAGIC_SIGNATURE = 'WARNING: magic method signature for %s does not appear to match required signature';
    const ERR_REMOVED         = 'WARNING: the following function has been removed: %s.  Use this instead: %s';
    const ERR_IS_RESOURCE     = 'WARNING: this function no longer produces a resource: %s.  Usage of "is_resource($item)" should be replaced with "!empty($item)';
    const ERR_MISSING_KEY     = 'ERROR: missing configuration key %s';
    const ERR_INVALID_KEY     = 'ERROR: this configuration key is either missing or not callable: ';
    const ERR_FILE_NOT_FOUND  = 'ERROR: file not found: %s';
    const WARN_BC_BREAKS      = 'WARNING: the code in this file might not be compatible with PHP 8';
    const NO_BC_BREAKS        = 'SUCCESS: the code scanned in this file is potentially compatible with PHP 8';
    const MAGIC_METHODS       = 'The following magic methods were detected:';
    const OK_PASSED           = 'PASSED this scan: %s';
    const TOTAL_BREAKS        = 'Total potential BC breaks: %d' . PHP_EOL;
    const KEY_REMOVED         = 'removed';
    const KEY_CALLBACK        = 'callbacks';
    const KEY_MAGIC           = 'magic';
    const KEY_RESOURCE        = 'resource';

    public $config = [];
    public $contents = '';
    public $messages = [];
    public $magic = [];
    
    /**
     * @param array $config : scan config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $required = [self::KEY_CALLBACK, self::KEY_REMOVED, self::KEY_MAGIC, self::KEY_RESOURCE];
        foreach ($required as $key) {
            if (!isset($this->config[$key])) {
                $message = sprintf(self::ERR_MISSING_KEY, $key);
                throw new InvalidArgumentException($message);
            }
        }
    }

    /**
     * Grabs contents
     * Initializes messages to []
     * Converts "\r" and "\n" to ' '
     *
     * @param string $fn    : name of file to scan
     * @return string $name : classnames
     */
    public function getFileContents(string $fn) : string
    {
        if (!file_exists($fn)) {
            $this->contents  = '';
            throw new InvalidArgumentException(sprintf(self::ERR_FILE_NOT_FOUND, $fn));
        }
        $this->clearMessages();
        $this->contents = file_get_contents($fn);
        $this->contents = str_replace(["\r","\n"],['', ' '], $this->contents);
        return $this->contents;
    }

    /**
     * Extracts the value immediately following the supplied word up until the supplied end
     *
     * @param string $contents : text to search (usually $this->contents)
     * @param string $key   : starting keyword or set of characters
     * @param string $delim : ending delimiter
     * @return string $name : classnames
     */
    public static function getKeyValue(string $contents, string $key, string $delim)
    {
        $pos = strpos($contents, $key);
        if ($pos === FALSE) return '';
        $end = strpos($contents, $delim, $pos + strlen($key) + 1);
        $key = substr($contents, $pos + strlen($key), $end - $pos - strlen($key));
        if (is_string($key)) {
            $key = trim($key);
        } else {
            $key = '';
        }
        $key = trim($key);
        return $key;
    }

    /**
     * Clears messages
     *
     * @return void
     */
    public function clearMessages() : void
    {
        $this->messages = [];
        $this->magic    = [];
    }

    /**
     * Returns messages
     *
     * @param bool $clear      : If TRUE, reset messages to []
     * @return array $messages : accumulated messages
     */
    public function getMessages(bool $clear = FALSE) : array
    {
        $messages = $this->messages;
        if ($clear) $this->clearMessages();
        return $messages;
    }

    /**
     * Returns 0 and adds OK message
     *
     * @param string $function
     * @return int 0
     */
    public function passedOK(string $function) : int
    {
        $this->messages[] = sprintf(self::OK_PASSED, $function);
        return 0;
    }

    /**
     * Runs all scans
     *
     * @return int $found : number of potential BC breaks found
     */
    public function runAllScans() : int
    {
        $found = 0;
        $found += $this->scanRemovedFunctions();
        $found += $this->scanIsResource();
        $found += $this->scanMagicSignatures();
        echo __METHOD__ . ':' . var_export($this->messages, TRUE) . "\n";
        $found += $this->scanFromCallbacks();
        return $found;
    }
    /**
     * Check for removed functions
     *
     * @return int $found : number of BC breaks detected
     */
    public function scanRemovedFunctions() : int
    {
        $found = 0;
        $config = $this->config[self::KEY_REMOVED] ?? NULL;
        // we add this extra safety check in case this method is called separately
        if (empty($config)) {
            $message = sprintf(self::ERR_MISSING_KEY, self::KEY_REMOVED);
            throw new Exception($message);
        }
        foreach ($config as $func => $replace) {
            $search1 = ' ' . $func . '(';
            $search2 = ' ' . $func . ' (';
            if (strpos($this->contents, $search1) !== FALSE
                || strpos($this->contents, $search2) !== FALSE) {
                $this->messages[] = sprintf(self::ERR_REMOVED, $func, $replace);
                $found++;
            }
        }
        return ($found === 0) ? $this->passedOK(__FUNCTION__) : $found;
    }
    /**
     * Check for is_resource usage
     * If "is_resource" found, check against list of functions
     * that no longer produce resources in PHP 8
     *
     * @return int $found : number of BC breaks detected
     */
    public function scanIsResource() : int
    {
        $found = 0;
        $search = 'is_resource';
        // if "is_resource" not found discontinue search
        if (strpos($this->contents, $search) === FALSE) return $this->passedOK(__FUNCTION__);
        // pull list of functions that now return objects instead of resources
        $config = $this->config[self::KEY_RESOURCE] ?? NULL;
        // we add this extra safety check in case this method is called separately
        if (empty($config)) {
            $message = sprintf(self::ERR_MISSING_KEY, self::KEY_RESOURCE);
            throw new Exception($message);
        }
        foreach ($config as $func) {
            if ((strpos($this->contents, $func) !== FALSE)) {
                $this->messages[] = sprintf(self::ERR_IS_RESOURCE, $func);
                $found++;
            }
        }
        return ($found === 0) ? $this->passedOK(__FUNCTION__) : $found;
    }
    /**
     * Scan for magic method signatures
     * NOTE: doesn't check inside parentheses.
     *       only checks for return data type + displays found and correct signatures for manual comparison
     *
     * @return int $found : number of invalid return data types
     */
    public function scanMagicSignatures() : int
    {
        // locate all magic methods
        $found   = 0;
        $matches = [];

        if (!empty($matches[1])) {
            $this->messages[] = self::MAGIC_METHODS;
            $config = $this->config[self::KEY_MAGIC] ?? NULL;
            // we add this extra safety check in case this method is called separately
            if (empty($config)) {
                $message = sprintf(self::ERR_MISSING_KEY, self::KEY_MAGIC);
                throw new Exception($message);
            }
            foreach ($matches[1] as $name) {
                $key = '__' . $name;
                // skip if key not found.  must not be a defined magic method
                if (!isset($config[$key])) continue;
                // record official signature
                $this->messages[] = 'Signature: ' . ($config[$key]['signature'] ?? 'Signature not found');
                $sub = $this->getKeyValue($this->contents, $key, '{');
                if ($sub) {
                    $sub = $key . $sub;
                    // record found signature
                    $this->messages[] = 'Actual   : ' . $sub;
                    // look for return type
                    if (strpos($sub, ':')) {
                        $ptn = '/.*?\(.*?\)\s*:\s*' . $config[$key]['return'] . '/';
                        // test for a match
                        if (!preg_match($ptn, $sub)) {
                            $this->messages[] = sprintf(self::ERR_MAGIC_SIGNATURE, $key);
                            $found++;
                        }
                    }
                }
            }
        }
        //echo __METHOD__ . ':' . var_export($this->messages, TRUE) . "\n";
        return ($found === 0) ? $this->passedOK(__FUNCTION__) : $found;
    }
    /**
     * Runs all scans key as defined in $this->config (bc_break_scanner.config.php)
     *
     * @return int $found : number of potential BC breaks found
     */
    public function scanFromCallbacks()
    {
        $found = 0;
        $list = array_keys($this->config[self::KEY_CALLBACK]);
        foreach ($list as $key) {
            $config = $this->config[self::KEY_CALLBACK][$key] ?? NULL;
            if (empty($config['callback']) || !is_callable($config['callback'])) {
                $message = sprintf(self::ERR_INVALID_KEY, self::KEY_CALLBACK . ' => ' . $key . ' => callback');
                throw new InvalidArgumentException($message);
            }
            if ($config['callback']($this->contents)) {
                $this->messages[] = $config['msg'];
                $found++;
            }
        }
        return $found;
    }

    public function homozygous_snps(string $chrom = "")
    {
        trigger_error("This method has been renamed to `homozygous`.", E_USER_DEPRECATED);
        return $this->homozygous($chrom);
    }

    public function is_valid()
    {
        trigger_error("This method has been renamed to `valid` and is now a property.", E_USER_DEPRECATED);
        return $this->valid;
    }

    public function predict_ancestry(
        ?string $output_directory = null,
        bool $write_predictions = false,
        ?string $models_directory = null,
        ?string $aisnps_directory = null,
        ?int $n_components = null,
        ?int $k = null,
        ?string $thousand_genomes_directory = null,
        ?string $samples_directory = null,
        ?string $algorithm = null,
        ?string $aisnps_set = null
    ) {
        // Method implementation goes here  
    }
    public function getPredictions(
        $output_directory,
        $write_predictions,
        $models_directory,
        $aisnps_directory,
        $n_components,
        $k,
        $thousand_genomes_directory,
        $samples_directory,
        $algorithm,
        $aisnps_set
    ) {
        if (!$this->valid) {
            // If the object is not valid, return an empty array
            return [];
        }

        // Check if ezancestry package is installed
        if (!class_exists('ezancestry\commands\Predict')) {
            // Throw an exception if the ezancestry package is not installed
            throw new Exception('Ancestry prediction requires the ezancestry package; please install it');
        }

        $predict = new ezancestry\commands\Predict();

        // Call the predict method of the ezancestry\commands\Predict class
        $predictions = $predict->predict(
            $this->snps,
            $output_directory,
            $write_predictions,
            $models_directory,
            $aisne_directory,
            $n_components,
            $k,
            $thousand_genomes_directory,
            $samples_directory,
            $algorithm,
            $aisnps_set
        );

        // Get the maxPop values from the first prediction
        $maxPopValues = $this->maxPop($predictions[0]);

        // Add the predictions to the maxPopValues array
        $maxPopValues['ezancestry_df'] = $predictions;

        // Return the maxPopValues array
        return $maxPopValues;        
    }
    
    private function maxPop($row)
    {
        // Extract the values from the $row array
        $popcode = $row['predicted_population_population'];
        $popdesc = $row['population_description'];
        $poppct = $row[$popcode];
        $superpopcode = $row['predicted_population_superpopulation'];
        $superpopdesc = $row['superpopulation_name'];
        $superpoppct = $row[$superpopcode];

        // Return an array with the extracted values
        return [
            'population_code' => $popcode,
            'population_description' => $popdesc,
            '_percent' => $poppct,
            'superpopulation_code' => $superpopcode,
            'superpopulation_description' => $superpopdesc,
            'population_percent' => $superpoppct,
        ];
    }
    
    /**
     * Computes cluster overlap based on given threshold.
     *
     * @param float $cluster_overlap_threshold The threshold for cluster overlap.
     * @return DataFrame The computed cluster overlap DataFrame.
     */
    public function compute_cluster_overlap($cluster_overlap_threshold = 0.95) {
        // Sample data for cluster overlap computation
        $data = [
            "cluster_id" => ["c1", "c3", "c4", "c5", "v5"],
            "company_composition" => [
                "23andMe-v4",
                "AncestryDNA-v1, FTDNA, MyHeritage",
                "23andMe-v3",
                "AncestryDNA-v2",
                "23andMe-v5, LivingDNA",
            ],
            "chip_base_deduced" => [
                "HTS iSelect HD",
                "OmniExpress",
                "OmniExpress plus",
                "OmniExpress plus",
                "Illumina GSAs",
            ],
            "snps_in_cluster" => array_fill(0, 5, 0),
            "snps_in_common" => array_fill(0, 5, 0),
        ];

        // Create a DataFrame from the data and set "cluster_id" as the index
        $df = new DataFrame($data);
        $df->setIndex("cluster_id");

        $to_remap = null;
        if ($this->build != 37) {
            // Create a clone of the current object for remapping
            $to_remap = clone $this;
            $to_remap->remap(37); // clusters are relative to Build 37
            $self_snps = $to_remap->snps()->select(["chrom", "pos"])->dropDuplicates();
        } else {
            $self_snps = $this->snps()->select(["chrom", "pos"])->dropDuplicates();
        }

        // Retrieve chip clusters from resources
        $chip_clusters = $this->resources->get_chip_clusters();

        // Iterate over each cluster in the DataFrame
        foreach ($df->indexValues() as $cluster) {
            // Filter chip clusters based on the current cluster
            $cluster_snps = $chip_clusters->filter(function ($row) use ($cluster) {
                return strpos($row["clusters"], $cluster) !== false;
            })->select(["chrom", "pos"]);

            // Update the DataFrame with the number of SNPs in the cluster and in common with the current object
            $df->loc[$cluster]["snps_in_cluster"] = count($cluster_snps);
            $df->loc[$cluster]["snps_in_common"] = count($self_snps->merge($cluster_snps, "inner"));

            // Calculate overlap ratios for cluster and self
            $df["overlap_with_cluster"] = $df["snps_in_common"] / $df["snps_in_cluster"];
            $df["overlap_with_self"] = $df["snps_in_common"] / count($self_snps);

            // Find the cluster with the maximum overlap
            $max_overlap = array_keys($df["overlap_with_cluster"], max($df["overlap_with_cluster"]))[0];

            // Check if the maximum overlap exceeds the threshold for both cluster and self
            if (
                $df["overlap_with_cluster"][$max_overlap] > $cluster_overlap_threshold &&
                $df["overlap_with_self"][$max_overlap] > $cluster_overlap_threshold
            ) {
                // Update the current object's cluster and chip based on the maximum overlap
                $this->cluster = $max_overlap;
                $this->chip = $df["chip_base_deduced"][$max_overlap];

                $company_composition = $df["company_composition"][$max_overlap];

                // Check if the current object's source is present in the company composition
                if (strpos($company_composition, $this->source) !== false) {
                    if ($this->source === "23andMe" || $this->source === "AncestryDNA") {
                        // Extract the chip version from the company composition
                        $i = strpos($company_composition, "v");
                        $this->chip_version = substr($company_composition, $i, $i + 2);
                    }
                } else {
                    // Log a warning about the SNPs data source not found
                }
            }
        }

        // Return the computed cluster overlap DataFrame
        return $df;
    }
}
<?php

/**
 * php-dna.
 *
 * tools for genetic genealogy and the analysis of consumer DNA test results
 *
 * @author          Devmanateam <devmanateam@outlook.com>
 * @copyright       Copyright (c) 2020-2023, Devmanateam
 * @license         MIT
 *
 * @link            http://github.com/familytree365/php-dna
 */

namespace Dna\Snps;

/**
 * Class SNPsResources.
 */
class SNPsResources extends Singleton
{
    /**
     * The directory where the resources are located
     * @var string
     */
    private string $_resources_dir;

    /**
     * The Ensembl REST client used to retrieve resources
     * @var EnsemblRestClient
     */
    private EnsemblRestClient $_ensembl_rest_client;

    /**
     * Constructor for the ResourceManager class
     * @param string $resources_dir The directory where the resources are located
     */

    public function __construct(string $resources_dir = "resources") {
        $this->_resources_dir = realpath($resources_dir);
        $this->_ensembl_rest_client = new EnsemblRestClient();
        $this->_init_resource_attributes();
    }

    /**
     * An array of reference sequences
     * @var array
     */
    private array $_reference_sequences;

    /**
     * A map of GSA RSIDs to chromosome positions
     * @var array|null
     */
    private ?array $_gsa_rsid_map;

    /**
     * A map of GSA chromosome positions to RSIDs
     * @var array|null
     */
    private ?array $_gsa_chrpos_map;

    /**
     * A map of dbSNP 151 to GRCh37 reverse mappings
     * @var array|null
     */
    private ?array $_dbsnp_151_37_reverse;

    /**
     * An array of filenames for the OpenSNP datadump
     * @var array
     */
    private array $_opensnp_datadump_filenames;

    /**
     * A map of chip clusters
     * @var array|null
     */
    private ?array $_chip_clusters;

    /**
     * An array of low quality SNPs
     * @var array|null
     */
    private ?array $_low_quality_snps;
    private function _init_resource_attributes(): void {
      $this->_reference_sequences = [];
      $this->_gsa_rsid_map = null;
      $this->_gsa_chrpos_map = null;
      $this->_dbsnp_151_37_reverse = null;
      $this->_opensnp_datadump_filenames = [];
      $this->_chip_clusters = null;
      $this->_low_quality_snps = null;
  }
  
    /**
     * An array of reference sequences
     * @var array
     */
    private $referenceSequences = [];

    /**
     * Retrieves reference sequences for the specified assembly and chromosomes
     * @param string $assembly The assembly to retrieve reference sequences for
     * @param array $chroms The chromosomes to retrieve reference sequences for
     * @return array An array of reference sequences
     */
    public function getReferenceSequences(
        string $assembly = "GRCh37",
        array $chroms = [
            "1", "2", "3", "4", "5", "6", "7", "8", "9", "10",
            "11", "12", "13", "14", "15", "16", "17", "18", "19", "20",
            "21", "22", "X", "Y", "MT",
        ]
    ): array {
        $validAssemblies = ["NCBI36", "GRCh37", "GRCh38"];

        if (!in_array($assembly, $validAssemblies)) {
            error_log("Invalid assembly");
            return [];
        }

        if (!$this->referenceChromsAvailable($assembly, $chroms)) {
            $this->referenceSequences[$assembly] = $this->createReferenceSequences(
                ...$this->getPathsReferenceSequences(assembly: $assembly, chroms: $chroms)
            );
        }

        return $this->referenceSequences[$assembly];
    }

    /**
     * Checks if reference chromosomes are available for the specified assembly and chromosomes
     * @param string $assembly The assembly to check reference chromosomes for
     * @param array $chroms The chromosomes to check reference chromosomes for
     * @return bool True if reference chromosomes are available, false otherwise
     */
    private function referenceChromsAvailable(string $assembly, array $chroms): bool {
        // TODO: Implement reference chromosome availability check
        return false;
    }

    /**
     * Creates reference sequences from the specified paths
     * @param string $fastaPath The path to the FASTA file
     * @param string $faiPath The path to the FAI file
     * @param string $dictPath The path to the dictionary file
     * @return array An array of reference sequences
     */
    private function createReferenceSequences(string $fastaPath, string $faiPath, string $dictPath): array {
        // TODO: Implement reference sequence creation
        return [];
    }

    /**
     * Retrieves paths to reference sequences for the specified assembly and chromosomes
     * @param string $assembly The assembly to retrieve reference sequence paths for
     * @param array $chroms The chromosomes to retrieve reference sequence paths for
     * @return array An array of paths to reference sequences
     */
    private function getPathsReferenceSequences(string $assembly, array $chroms): array {
        // TODO: Implement reference sequence path retrieval
        return [];
    }
    
    /**
     * Get assembly mapping data.
     *
     * @param string $sourceAssembly {'NCBI36', 'GRCh37', 'GRCh38'} assembly to remap from
     * @param string $targetAssembly {'NCBI36', 'GRCh37', 'GRCh38'} assembly to remap to
     *
     * @return array array of json assembly mapping data if loading was successful, else []
     */
    public function getAssemblyMappingData(string $sourceAssembly, string $targetAssembly): array {
      // Get assembly mapping data.
      return $this->loadAssemblyMappingData(
          $this->getPathAssemblyMappingData($sourceAssembly, $targetAssembly)
      );
  }

  /**
   * Downloads example datasets.
   *
   * @return array Array of downloaded file paths.
   */
  public function download_example_datasets(): array 
  {
      $paths = [];

      // Download 23andMe example dataset.
      $paths[] = $this->_download_file("https://opensnp.org/data/662.23andme.340", "662.23andme.340.txt.gz", true);

      // Download FTDNA Illumina example dataset.
      $paths[] = $this->_download_file("https://opensnp.org/data/662.ftdna-illumina.341", "662.ftdna-illumina.341.csv.gz", true);

      return $paths;
  }  

/**
 * Gets / downloads all resources used throughout snps.
 *
 * @return array Array of resources.
 */
  public function getAllResources()
  {
      // Get / download all resources used throughout snps.
      //
      // Notes
      // -----
      // This function does not download reference sequences and the openSNP datadump,
      // due to their large sizes.
      //
      // Returns
      // -------
      // array of resources

      $resources = [];
      $versions = ["NCBI36", "GRCh37", "GRCh38"];

      // Loop through all possible assembly mappings and get their data.
      for ($i = 0; $i < count($versions); ++$i) {
          for ($j = 0; $j < count($versions); ++$j) {
              if ($i === $j) {
                  continue;
              }
              $source = $versions[$i];
              $target = $versions[$j];
              $resources[$source . "_" . $target] = $this->getAssemblyMappingData($source, $target);
          }
      }

      // Get GSA resources.
      $resources["gsa_resources"] = $this->getGsaResources();

      // Get chip clusters.
      $resources["chip_clusters"] = $this->getChipClusters();

      // Get low quality SNPs.
      $resources["low_quality_snps"] = $this->getLowQualitySnps();

      return $resources;
  }

  /**
   * Gets Homo sapiens reference sequences for Builds 36, 37, and 38 from Ensembl.
   *
   * @param mixed ...$args Additional arguments to pass to getReferenceSequences.
   *
   * @return array Dictionary of ReferenceSequence, else {}.
   */
  public function getAllReferenceSequences(...$args): array
  {
      /**
       * Get Homo sapiens reference sequences for Builds 36, 37, and 38 from Ensembl.
       *
       * Notes
       * -----
       * This function can download over 2..
       *
       * Returns
       * -------
       * dict
       *   dict of ReferenceSequence, else {}
       */

      $assemblies = ["NCBI36", "GRCh37", "GRCh38"];

      // Loop through all assemblies and get their reference sequences.
      foreach ($assemblies as $assembly) {
          $this->getReferenceSequences($assembly, ...$args);
      }

      return $this->reference_sequences;
  }

  /**
   * Get resources for reading Global Screening Array files.
   * https://support.illumina.com/downloads/infinium-global-screening-array-v2-0-product-files.html
   *
   * @return array An array of resources for reading Global Screening Array files.
   */
  public function getGsaResources(): array
  {
      // Get the rsid map resource.
      $rsid_map = $this->getGsaRsid();

      // Get the chrpos map resource.
      $chrpos_map = $this->getGsaChrpos();

      // Get the dbsnp 151 37 reverse resource.
      $dbsnp_151_37_reverse = $this->getDbsnp15137Reverse();

      // Return an array of resources.
      return [
          "rsid_map" => $rsid_map,
          "chrpos_map" => $chrpos_map,
          "dbsnp_151_37_reverse" => $dbsnp_151_37_reverse,
      ];
  }
  
  /**
   * Get the chip clusters data.
   *
   * @return DataFrame The chip clusters data.
   */
  public function get_chip_clusters() {
    // If the chip clusters data has not been loaded yet, download and process it.
    if ($this->_chip_clusters === null) {
        // Download the chip clusters file.
        $chip_clusters_path = $this->_download_file(
            "https://supfam.mrc-lmb.cam.ac.uk/GenomePrep/datadir/the_list.tsv.gz",
            "chip_clusters.tsv.gz"
        );

        // Load the chip clusters file into a DataFrame.
        $df = \DataFrame::from_csv($chip_clusters_path, [
            'sep' => "\t",
            'names' => ["locus", "clusters"],
            'dtypes' => [
                "locus" => 'string', "clusters" => \CategoricalDtype::create(['ordered' => false])
            ]
        ]);

        // Split the locus column into separate columns for chromosome and position.
        $clusters = $df['clusters'];
        $locus = $df['locus']->str_split(":", ['expand' => true]);
        $locus->rename(['chrom', 'pos'], ['axis' => 1, 'inplace' => true]);

        // Convert the position column to an unsigned 32-bit integer.
        $locus['pos'] = $locus['pos']->astype('uint32');

        // Convert the chromosome column to a categorical data type.
        $locus['chrom'] = $locus['chrom']->astype(\CategoricalDtype::create(['ordered' => false]));

        // Add the clusters column to the locus DataFrame.
        $locus['clusters'] = $clusters;

        // Save the processed chip clusters data to the object.
        $this->_chip_clusters = $locus;
    }

    // Return the chip clusters data.
    return $this->_chip_clusters;
  }
  
  /**
   * Get the low quality SNPs data.
   *
   * @return array The low quality SNPs data.
   */
  private ?array $_lowQualitySnps = null;

  public function getLowQualitySNPs(): array
  {
      // If the low quality SNPs data has not been loaded yet, download and process it.
      if ($this->_lowQualitySnps === null) {
          // Download the low quality SNPs file.
          $lowQualitySnpsPath = $this->downloadFile(
              "https://supfam.mrc-lmb.cam.ac.uk/GenomePrep/datadir/badalleles.tsv.gz",
              "low_quality_snps.tsv.gz"
          );

          // Load the low quality SNPs file into an array.
          $fileContents = file_get_contents($lowQualitySnpsPath);
          $rows = explode("\n", $fileContents);

          // Process the low quality SNPs data into an array of arrays.
          $clusterDfs = [];

          foreach ($rows as $row) {
              if (empty($row)) {
                  continue;
              }

              [$cluster, $loci] = explode("\t", $row);
              $lociSplit = explode(",", $loci);

              foreach ($lociSplit as $locus) {
                  $clusterDfs[] = ['cluster' => $cluster, 'locus' => $locus];
              }
          }

          // Transform the low quality SNPs data into an array of arrays with separate columns for chromosome and position.
          $transformedData = [];

          foreach ($clusterDfs as $clusterDf) {
              [$chrom, $pos] = explode(':', $clusterDf['locus']);
              $transformedData[] = [
                  'cluster' => $clusterDf['cluster'],
                  'chrom' => $chrom,
                  'pos' => intval($pos)
              ];
          }

          // Save the processed low quality SNPs data to the object.
          $this->_lowQualitySnps = $transformedData;
      }

      // Return the low quality SNPs data.
      return $this->_lowQualitySnps;
  }  

  /**
   * Get the dbsnp 151 37 reverse data.
   *
   * @return array|null The dbsnp 151 37 reverse data.
   */
  public function get_dbsnp_151_37_reverse(): ?array {
    // If the dbsnp 151 37 reverse data has not been loaded yet, download and process it.
    if ($this->_dbsnp_151_37_reverse === null) {
        // Download the dbsnp 151 37 reverse file.
        $dbsnp_rev_path = $this->download_file(
            "https://sano-public.s3.eu-west-2.amazonaws.com/dbsnp151.b37.snps_reverse.txt.gz",
            "dbsnp_151_37_reverse.txt.gz"
        );

        // Load the dbsnp 151 37 reverse file into an array.
        $rsids = array();
        $file_handle = fopen($dbsnp_rev_path, "r");
        while (!feof($file_handle)) {
            $line = fgets($file_handle);
            if ($line[0] !== "#") {
                $tokens = explode(" ", trim($line));
                if (count($tokens) === 5) {
                    $rsid = array(
                        "dbsnp151revrsid" => $tokens[0],
                        "dbsnp151freqa" => (double)$tokens[1],
                        "dbsnp151freqt" => (double)$tokens[2],
                        "dbsnp151freqc" => (double)$tokens[3],
                        "dbsnp151freqg" => (double)$tokens[4]
                    );
                    $rsids[] = $rsid;
                }
            }
        }
        fclose($file_handle);

        // Save the processed dbsnp 151 37 reverse data to the object.
        $this->_dbsnp_151_37_reverse = $rsids;
    }

    // Return the dbsnp 151 37 reverse data.
    return $this->_dbsnp_151_37_reverse;
  }
  
  /**
   * Get the filenames of the OpenSNP datadump files.
   *
   * @return array The filenames of the OpenSNP datadump files.
   */
  public function getOpensnpDatadumpFilenames(): array {
    // If the OpenSNP datadump filenames have not been loaded yet, load them from the path.
    if (!$this->opensnpDatadumpFilenames) {
        $this->opensnpDatadumpFilenames = $this->getOpensnpDatadumpFilenamesFromPath(
            $this->getPathOpensnpDatadump()
        );
    }
    // Return the OpenSNP datadump filenames.
    return $this->opensnpDatadumpFilenames;
  }
  
  /**
   * Write data to a gzip file.
   *
   * @param string $filename The name of the gzip file to write to.
   * @param string $data The data to write to the gzip file.
   */
  function writeDataToGzip(string $filename, string $data) {
    // Open the gzip file for writing.
    $fGzip = gzopen($filename, 'wb');

    // Write the data to the gzip file.
    gzwrite($fGzip, $data);

    // Close the gzip file.
    gzclose($fGzip);
  }
  
  /**
   * Load assembly mapping data from a tar file.
   *
   * @param string $filename The name of the tar file to load the assembly mapping data from.
   * @return array The assembly mapping data.
   */
  public static function loadAssemblyMappingData(string $filename): array {
    // Initialize an empty array to store the assembly mapping data.
    $assembly_mapping_data = [];

    // Create a new PharData object from the tar file.
    $tar = new PharData($filename);

    // Iterate over each file in the tar file.
    foreach ($tar as $tarinfo) {
        $member_name = $tarinfo->getFilename();

        // If the file is a JSON file, load its contents into an array and add it to the assembly mapping data.
        if (str_contains($member_name, ".json")) {
            $tarfile = $tar->offsetGet($member_name)->getContent();
            $tar_bytes = $tarfile;
            $assembly_mapping_data[explode(".", $member_name)[0]] = json_decode($tar_bytes, true);
        }
    }

    // Return the assembly mapping data.
    return $assembly_mapping_data;
  }
  
  /**
   * Get the paths to reference sequences for a given assembly and set of chromosomes.
   *
   * @param string $sub_dir The subdirectory to download the reference sequences to.
   * @param string $assembly The assembly to get the reference sequences for.
   * @param array $chroms The chromosomes to get the reference sequences for.
   * @return array An array containing the assembly, chromosomes, URLs, and local filenames of the reference sequences.
   */
  public function getPathsReferenceSequences(
    string $sub_dir = "fasta",
    string $assembly = "GRCh37",
    array $chroms = []
  ): array {
    // Determine the base URL and release number based on the assembly.
    if ($assembly === "GRCh37") {
        $base = "ftp://ftp.ensembl.org/pub/grch37/release-96/fasta/homo_sapiens/dna/";
        $release = "";
    } elseif ($assembly === "NCBI36") {
        $base = "ftp://ftp.ensembl.org/pub/release-54/fasta/homo_sapiens/dna/";
        $release = "54.";
    } elseif ($assembly === "GRCh38") {
        $base = "ftp://ftp.ensembl.org/pub/release-96/fasta/homo_sapiens/dna/";
        $release = "";
    } else {
        // If the assembly is not recognized, return an empty array.
        return ["", [], [], []];
    }

    // Generate the filenames, URLs, and local filenames for the reference sequences.
    $filenames = array_map(
        fn($chrom) => "Homo_sapiens.{$assembly}.{$release}dna.chromosome.{$chrom}.fa.gz",
        $chroms
    );

    $urls = array_map(fn($filename) => "{$base}{$filename}", $filenames);

    $local_filenames = array_map(
        fn($filename) => "{$sub_dir}" . DIRECTORY_SEPARATOR . "{$assembly}" . DIRECTORY_SEPARATOR . "{$filename}",
        $filenames
    );

    // Download the reference sequences and return the assembly, chromosomes, URLs, and local filenames.
    $downloads = array_map([$this, "downloadFile"], $urls, $local_filenames);

    return [$assembly, $chroms, $urls, $downloads];
  }
  
  /**
   * Create reference sequences from downloaded files.
   *
   * @param string $assembly The assembly of the reference sequences.
   * @param array $chroms The chromosomes of the reference sequences.
   * @param array $urls The URLs of the reference sequences.
   * @param array $paths The paths of the downloaded reference sequence files.
   * @return array An array of ReferenceSequence objects.
   */
  public function create_reference_sequences($assembly, $chroms, $urls, $paths)
  {
      // Initialize an empty array to store the reference sequences.
      $seqs = [];

      // Iterate over each downloaded reference sequence file.
      foreach ($paths as $i => $path) {
          // If the path is empty, skip this reference sequence.
          if (!$path) {
              continue;
          }

          // Create a dictionary to store information about the reference sequence.
          $d = [];

          // Add the chromosome, URL, path, assembly, species, and taxonomy to the dictionary.
          $d["ID"] = $chroms[$i];
          $d["url"] = $urls[$i];
          $d["path"] = realpath($path);
          $d["assembly"] = $assembly;
          $d["species"] = "Homo sapiens";
          $d["taxonomy"] = "x";

          // Create a new ReferenceSequence object from the dictionary and add it to the array of reference sequences.
          $seqs[$chroms[$i]] = new ReferenceSequence($d);
      }

      // Return the array of reference sequences.
      return $seqs;
  }
  
  /**
   * Get the path to the assembly mapping data for a given source and target assembly.
   *
   * @param string $source_assembly The source assembly of the assembly mapping data.
   * @param string $target_assembly The target assembly of the assembly mapping data.
   * @param int $retries The number of times to retry downloading the assembly mapping data if it fails.
   * @return string The path to the assembly mapping data.
   */
  public function getPathAssemblyMappingData(string $source_assembly, string $target_assembly, int $retries = 10): string
  {
      // Create the resources directory if it does not exist.
      if (!$this->createDir($this->resources_dir)) {
          return "";
      }

      // Generate the list of chromosomes to download the assembly mapping data for.
      $chroms = [];
      for ($i = 1; $i <= 22; $i++) {
          $chroms[] = (string)$i;
      }
      $chroms = array_merge($chroms, ["X", "Y", "MT"]);

      // Generate the name of the assembly mapping data file and its destination path.
      $assembly_mapping_data = $source_assembly . "_" . $target_assembly;
      $destination = $this->resources_dir . DIRECTORY_SEPARATOR . $assembly_mapping_data . ".tar.gz";

      // If the assembly mapping data file does not exist, download it.
      if (!file_exists($destination)) {
          if ($this->logger) {
              $this->logger->info("Downloading " . str_replace(getcwd(), ".", $destination));
          }
          $this->downloadAssemblyMappingData($destination, $chroms, $source_assembly, $target_assembly, $retries);
      }

      // Return the path to the assembly mapping data file.
      return $destination;
  }
  
  /**
   * Download assembly mapping data for a given set of chromosomes and save it to a tar file.
   *
   * @param string $destination The destination path of the tar file to save the assembly mapping data to.
   * @param array $chroms The chromosomes to download the assembly mapping data for.
   * @param string $sourceAssembly The source assembly of the assembly mapping data.
   * @param string $targetAssembly The target assembly of the assembly mapping data.
   * @param int $retries The number of times to retry downloading the assembly mapping data if it fails.
   */
  public function downloadAssemblyMappingData($destination, $chroms, $sourceAssembly, $targetAssembly, $retries) {
    // Create a new PharData object for the destination tar file.
    $phar = new PharData($destination, 0, null, Phar::TAR | Phar::GZ);

    // Iterate over each chromosome and download its assembly mapping data.
    foreach ($chroms as $chrom) {
        $file = "$chrom.json";
        $mapEndpoint = "/map/human/{$sourceAssembly}/{$chrom}/{$targetAssembly}?";

        // Get the assembly mapping data from the Ensembl REST API.
        $response = null;
        $retry = 0;
        while ($response === null && $retry < $retries) {
            $response = $this->ensemblRestClient->performRestAction($mapEndpoint);
            $retry++;
        }

        if ($response !== null) {
            // Save the assembly mapping data to a temporary file.
            $tmpFile = tempnam(sys_get_temp_dir(), "tmp");
            file_put_contents($tmpFile, json_encode($response));

            // Add the temporary file to the tar file.
            $phar->addFile($tmpFile, $file);

            // Remove the temporary file.
            unlink($tmpFile);
        }
    }
  }
  
public function getGsaRsid(): array|null {
        if ($this->gsa_rsid_map === null) {
            $url = "https://sano-public.s3.eu-west-2.amazonaws.com/gsa_rsid_map.txt.gz";
            $destination = "gsa_rsid_map.txt.gz";
            $this->downloadFile($url, $destination);

            $file_path = $this->processArchiveFile($destination);
            $this->gsa_rsid_map = $this->loadCsv($file_path);
        }

        return $this->gsa_rsid_map;
    }
  
}
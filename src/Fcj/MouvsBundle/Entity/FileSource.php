<?php

namespace Fcj\MouvsBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * FileSource
 *
 * todo: abstract? w/ impl. such as:
 *    FileSystemSource
 *        UserDirSource (i.e. ~dude/)
 *    RemoteSource (with e.g. caching/latency mecanisms?)
 *       SshSource, FtpSource, WebDavSource
 *
 *
 *
 * @ORM\Table()
 * @ORM\Entity
 */
class FileSource
{
    /**
     * @var integer
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(type="text")
     */
    protected $path;

    /**
     * @var ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="File", mappedBy="source",
     *    orphanRemoval=true, cascade={"all"},
     *    indexBy="inode"
     * )
     * todo: indexBy = "hash" ? or "inode" ?
     */
    protected $files;

    // todo: $owner? here?
    // todo: $visibility := IN private (to user), public (anyone) ?

    /**
     *
     */
    public function __construct()
    {
        $this->files = new ArrayCollection();
    }

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set path
     *
     * @param string $path
     * @return FileSource
     */
    public function setPath($path)
    {
        $this->path = $path;
    
        return $this;
    }

    /**
     * Get path
     *
     * @return string 
     */
    public function getPath()
    {
        return $this->path;
    }

    /** (Re-)Synchronize DB versus on-disk files.
     *
     * @return ArrayCollection of File instances, some already baked,
     *    others not in case of newly discovered files.
     */
    public function sync()
    {
        $finder = Finder::create()
            ->in($this->path)
            ->followLinks()
            ->files()
            ->sortByName();

        //$dbFiles = $fileSource->getFiles();
        //print_r ($dbFiles->getKeys());

        $i = 0;
        $files = new ArrayCollection();

        /** @var SplFileInfo $file */
        foreach($finder AS $file)
        {
            $i ++;
            try {
                //error_log("{$file->getFilename()} [{$file->getInode()}] ({$file->getSize()}, {$file->getRelativePath()})");
                //error_log("$i");
                $f = new File($file);
                $g = $this->addFile($f);
                $files[$g->getInode()] = $g;
            }
            catch(\RuntimeException $ex)
            {
                error_log(__METHOD__ . ": ERROR: Caught exception!: " . $ex->getMessage());
            }
        }

        return $files;
    }

    /**
     *
     * @param File $file
     * @throws \InvalidArgumentException
     * @return File
     */
    public function addFile(File $file)
    {
        $inode = $file->getInode();
        if ($inode && $this->files->containsKey($inode)) {
            //error_log("INFO: " .__METHOD__. ": Inode $inode is already baked.");
            error_log("U\t$inode\t{$file->getName()}");
            /** @var File $baked */
            $baked = $this->files->get($inode);
            if ($baked->getCTime() != $file->getCTime())
            {
                $baked->setName($file->getName());
                $baked->setPath($file->getPath());
                $baked->setCTime($file->getCTime());
                $baked->setLastUpdate();
            }
            if ($baked->getMTime() != $file->getMTime())
            {
                $baked->setSize($file->getSize());
                $baked->setHash(null);
                $baked->setMTime($file->getMTime());
                $baked->setLastUpdate();
            }
            return $baked;
        }
        else if ($inode) { // fixme !?
            //error_log("INFO: " .__METHOD__. ": Inode $inode NEW!!! ({$file->getName()}).");
            error_log("A\t$inode\t{$file->getName()}");
            $file->setSource($this);
            $file->setAddedOn();
            $this->files->set($inode, $file);
            return $file;
        }
        else // fixme: yes? no?
            throw new \InvalidArgumentException(__METHOD__ . ": ERROR: File has *NO* inode!!");
    }

    /**
     * Get files
     *
     *
     * @return ArrayCollection
     */
    public function getFiles()
    {
        return $this->files;
    }

}
